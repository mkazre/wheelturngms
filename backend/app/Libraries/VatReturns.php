<?php

namespace App\Libraries;

use Config\Database;
use RuntimeException;

/**
 * VAT201 periods. Figures come from the ledger (VAT output 2100 / VAT input 1300), so every
 * invoice, credit note, bill and expense is included. Submitting closes both accounts into
 * VAT control (2110); paying SARS clears 2110 against the bank.
 */
class VatReturns
{
    private static function vatMovement(string $code, string $from, string $to): float
    {
        $r = Database::connect()->table('journal_lines l')->select('COALESCE(SUM(l.debit),0) dr, COALESCE(SUM(l.credit),0) cr')
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->join('accounts a', 'a.id = l.account_id')
            ->where('a.code', $code)->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)
            ->whereNotIn('e.source_type', ['vat_return', 'vat_payment'])->get()->getRowArray();
        return round($code === '2100' ? (float) $r['cr'] - (float) $r['dr'] : (float) $r['dr'] - (float) $r['cr'], 2);
    }

    public static function calculate(string $from, string $to): array
    {
        $db = Database::connect();
        $outVat = self::vatMovement('2100', $from, $to);
        $inVat = self::vatMovement('1300', $from, $to);
        $sales = (float) $db->table('invoices')->select('COALESCE(SUM(subtotal - discount_total),0) v', false)->where('deleted_at', null)->whereNotIn('status', ['draft', 'void'])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getRow()->v;
        $credits = (float) $db->table('credit_notes')->select('COALESCE(SUM(subtotal - discount_total),0) v', false)->where(['status' => 'issued', 'deleted_at' => null])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getRow()->v;
        $exp = (float) $db->table('expenses')->selectSum('amount_excl')->where(['deleted_at' => null, 'is_vat_claimable' => 1])->where('expense_date >=', $from)->where('expense_date <=', $to)->get()->getRow()->amount_excl;
        $bills = (float) $db->table('supplier_bills')->select('COALESCE(SUM(subtotal - discount_total),0) v', false)->where('deleted_at', null)->where('status !=', 'void')->where('vat_total >', 0)->where('bill_date >=', $from)->where('bill_date <=', $to)->get()->getRow()->v;
        return [
            'output_base' => round($sales - $credits, 2), 'output_vat' => $outVat,
            'input_base' => round($exp + $bills, 2), 'input_vat' => $inVat,
            'net_vat' => round($outVat - $inVat, 2),
        ];
    }

    public static function submit(string $from, string $to, ?string $sarsRef = null): int
    {
        if ($to >= date('Y-m-d')) {
            throw new RuntimeException('This VAT period has not ended yet (ends ' . date('d M Y', strtotime($to)) . ').');
        }
        $c = self::calculate($from, $to);
        $db = Database::connect();
        $db->transStart();
        $db->table('vat_returns')->insert($c + [
            'period_start' => $from, 'period_end' => $to, 'status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s'),
            'sars_reference' => $sarsRef, 'created_by' => session('user_id'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertID();
        $lines = [
            ['account' => '2100', 'debit' => max(0, $c['output_vat']), 'credit' => max(0, -$c['output_vat']), 'description' => 'Close output VAT'],
            ['account' => '1300', 'credit' => max(0, $c['input_vat']), 'debit' => max(0, -$c['input_vat']), 'description' => 'Close input VAT'],
            ['account' => '2110', 'credit' => max(0, $c['net_vat']), 'debit' => max(0, -$c['net_vat']), 'description' => $c['net_vat'] >= 0 ? 'VAT payable to SARS' : 'VAT refund due from SARS'],
        ];
        Ledger::post('vat_return', $id, $to, 'VAT201 ' . date('M Y', strtotime($from)) . ' – ' . date('M Y', strtotime($to)), $lines, $sarsRef);
        $db->transComplete();
        Activity::log('vat_return', $id, 'submitted', 'VAT return recorded: net ' . money($c['net_vat']));
        return $id;
    }

    public static function pay(int $id, int $bankId, string $date): void
    {
        $db = Database::connect();
        $r = $db->table('vat_returns')->where('id', $id)->get()->getRowArray();
        if (!$r || $r['status'] !== 'submitted') {
            throw new RuntimeException('This VAT return is not awaiting payment.');
        }
        $net = (float) $r['net_vat'];
        $lines = $net >= 0
            ? [['account' => '2110', 'debit' => $net], ['account_id' => $bankId, 'credit' => $net]]
            : [['account_id' => $bankId, 'debit' => -$net], ['account' => '2110', 'credit' => -$net]];
        $db->transStart();
        Ledger::post('vat_payment', $id, $date, ($net >= 0 ? 'VAT paid to SARS' : 'VAT refund from SARS') . ' for ' . date('M Y', strtotime($r['period_end'])), $lines, $r['sars_reference']);
        $db->table('vat_returns')->where('id', $id)->update(['status' => 'paid', 'paid_at' => $date . ' 12:00:00', 'paid_from_id' => $bankId, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();
    }
}
