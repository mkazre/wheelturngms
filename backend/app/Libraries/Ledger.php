<?php

namespace App\Libraries;

use Config\Database;
use RuntimeException;

/**
 * Double-entry general ledger. Every operational document posts a balanced journal.
 * Re-posting a source replaces its previous journal, so edits stay consistent.
 */
class Ledger
{
    public const METHOD_ACCOUNTS = [
        'cash' => '1010', 'card' => '1020', 'eft' => '1000', 'payfast' => '1030', 'ozow' => '1040', 'other' => '1000', 'deposit' => '2300',
    ];

    private const REVENUE_MAP = [
        'labour' => '4000', 'service' => '4000',
        'part' => '4100', 'fluid' => '4100', 'tyre' => '4100',
        'sublet' => '4200', 'sundry' => '4200',
    ];

    private static array $accountIds = [];

    public static function accountId(string $code): int
    {
        if (!isset(self::$accountIds[$code])) {
            $row = Database::connect()->table('accounts')->select('id')->where('code', $code)->get()->getRowArray();
            if (!$row) {
                throw new RuntimeException("Ledger account {$code} not found. Run the installer seeder.");
            }
            self::$accountIds[$code] = (int) $row['id'];
        }
        return self::$accountIds[$code];
    }

    /**
     * @param array $lines [['account' => '4000' (code) OR 'account_id' => 12 (db id), 'debit' => x, 'credit' => y, 'description' => '', 'customer_id' => null]]
     */
    public static function post(string $sourceType, ?int $sourceId, string $date, string $description, array $lines, ?string $reference = null, ?int $branchId = null): ?int
    {
        $db = Database::connect();
        $lines = array_values(array_filter($lines, static fn ($l) => round((float) ($l['debit'] ?? 0), 2) != 0 || round((float) ($l['credit'] ?? 0), 2) != 0));

        $dr = round(array_sum(array_map(static fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $cr = round(array_sum(array_map(static fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        if (abs($dr - $cr) > 0.009) {
            throw new RuntimeException("Unbalanced journal for {$sourceType} #{$sourceId}: Dr {$dr} / Cr {$cr}");
        }

        self::assertOpen($date);
        $db->transStart();
        if ($sourceId !== null) {
            self::remove($sourceType, $sourceId);
        }
        if ($lines === []) {
            $db->transComplete();
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $db->table('journal_entries')->insert([
            'number' => Numbering::next('journal'), 'entry_date' => $date, 'reference' => $reference,
            'description' => mb_substr($description, 0, 500), 'source_type' => $sourceType, 'source_id' => $sourceId,
            'branch_id' => $branchId, 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $entryId = (int) $db->insertID();
        foreach ($lines as $l) {
            $db->table('journal_lines')->insert([
                'journal_entry_id' => $entryId,
                'account_id'       => isset($l['account_id']) ? (int) $l['account_id'] : self::accountId((string) $l['account']),
                'debit'            => round((float) ($l['debit'] ?? 0), 2),
                'credit'           => round((float) ($l['credit'] ?? 0), 2),
                'description'      => $l['description'] ?? null,
                'customer_id'      => $l['customer_id'] ?? null,
                'supplier_id'      => $l['supplier_id'] ?? null,
            ]);
        }
        $db->transComplete();
        return $entryId;
    }

    public static function remove(string $sourceType, int $sourceId): void
    {
        $db  = Database::connect();
        $existing = $db->table('journal_entries')->select('id, entry_date')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->get()->getResultArray();
        foreach ($existing as $e) {
            self::assertOpen($e['entry_date']);
        }
        $ids = array_column($existing, 'id');
        if ($ids) {
            $db->table('journal_lines')->whereIn('journal_entry_id', $ids)->delete();
            $db->table('journal_entries')->whereIn('id', $ids)->delete();
        }
    }

    public static function postInvoice(int $invoiceId): void
    {
        $db  = Database::connect();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if (!$inv || in_array($inv['status'], ['draft', 'void'], true)) {
            self::remove('invoice', $invoiceId);
            return;
        }
        $items = $db->table('line_items li')->select('li.*, p.track_stock')
            ->join('products p', 'p.id = li.product_id', 'left')
            ->where(['document_type' => 'invoice', 'document_id' => $invoiceId])->get()->getResultArray();

        $revenue = [];
        $cogs = 0.0;
        foreach ($items as $it) {
            $code = self::REVENUE_MAP[$it['item_type']] ?? '4200';
            $revenue[$code] = ($revenue[$code] ?? 0) + (float) $it['line_subtotal'] - (float) $it['line_discount'];
            if ((int) ($it['track_stock'] ?? 0) === 1) {
                $cogs += (float) $it['unit_cost'] * (float) $it['quantity'];
            }
        }
        $cid   = (int) $inv['customer_id'];
        $lines = [['account' => '1100', 'debit' => $inv['total'], 'description' => 'Invoice ' . $inv['number'], 'customer_id' => $cid]];
        foreach ($revenue as $code => $amt) {
            $lines[] = ['account' => (string) $code, 'credit' => round($amt, 2), 'customer_id' => $cid];
        }
        $lines[] = ['account' => '2100', 'credit' => $inv['vat_total'], 'description' => 'Output VAT'];
        // Absorb any rounding difference into the largest revenue line
        $diff = round((float) $inv['total'] - array_sum(array_map(static fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        if ($diff != 0 && count($lines) > 2) {
            $lines[1]['credit'] = round($lines[1]['credit'] + $diff, 2);
        }
        if ($cogs > 0) {
            $lines[] = ['account' => '5000', 'debit' => round($cogs, 2), 'description' => 'Cost of parts'];
            $lines[] = ['account' => '1200', 'credit' => round($cogs, 2), 'description' => 'Stock issued'];
        }
        self::post('invoice', $invoiceId, $inv['issue_date'], 'Tax invoice ' . $inv['number'], $lines, $inv['number'], $inv['branch_id'] ? (int) $inv['branch_id'] : null);
    }

    public static function postPayment(int $paymentId): void
    {
        $db = Database::connect();
        $p  = $db->table('payments')->where('id', $paymentId)->get()->getRowArray();
        if (!$p || $p['status'] !== 'completed') {
            self::remove('payment', $paymentId);
            return;
        }
        $acc = self::METHOD_ACCOUNTS[$p['method']] ?? '1000';
        self::post('payment', $paymentId, substr($p['paid_at'], 0, 10), 'Payment ' . $p['number'] . ' (' . strtoupper($p['method']) . ')', [
            ['account' => $acc, 'debit' => $p['amount']],
            ['account' => '1100', 'credit' => $p['amount'], 'customer_id' => (int) $p['customer_id']],
        ], $p['reference'] ?: $p['number'], $p['branch_id'] ? (int) $p['branch_id'] : null);
    }

    public static function postExpense(int $expenseId): void
    {
        $db = Database::connect();
        $e  = $db->table('expenses')->where('id', $expenseId)->where('deleted_at', null)->get()->getRowArray();
        if (!$e) {
            self::remove('expense', $expenseId);
            return;
        }
        $claim = (int) $e['is_vat_claimable'] === 1 && (float) $e['vat_amount'] > 0;
        $lines = [
            ['account_id' => (int) $e['account_id'], 'debit' => $claim ? $e['amount_excl'] : $e['total'], 'supplier_id' => $e['supplier_id']],
        ];
        if ($claim) {
            $lines[] = ['account' => '1300', 'debit' => $e['vat_amount'], 'description' => 'Input VAT'];
        }
        $lines[] = $e['paid_from_id'] ? ['account_id' => (int) $e['paid_from_id'], 'credit' => $e['total']] : ['account' => '1000', 'credit' => $e['total']];
        self::post('expense', $expenseId, $e['expense_date'], 'Expense ' . $e['number'] . ': ' . $e['description'], $lines, $e['reference'] ?: $e['number'], $e['branch_id'] ? (int) $e['branch_id'] : null);
    }

    /** Period lock: nothing may be posted or changed on/before the lock date. */
    public static function assertOpen(string $date): void
    {
        $lock = (string) Settings::get('lock_date', '');
        if ($lock !== '' && $date <= $lock) {
            throw new RuntimeException('The books are locked up to ' . date('d M Y', strtotime($lock)) . '. Change the lock date in Accounting → Period lock to post on ' . date('d M Y', strtotime($date)) . '.');
        }
    }

    /** Credit note: reverse revenue + output VAT against the debtor; optionally return stock at cost. */
    public static function postCreditNote(int $id): void
    {
        $db = Database::connect();
        $cn = $db->table('credit_notes')->where('id', $id)->get()->getRowArray();
        if (!$cn || $cn['status'] !== 'issued' || $cn['deleted_at']) {
            self::remove('credit_note', $id);
            return;
        }
        $items = $db->table('line_items li')->select('li.*, p.track_stock')->join('products p', 'p.id = li.product_id', 'left')
            ->where(['document_type' => 'credit_note', 'document_id' => $id])->get()->getResultArray();
        $cid = (int) $cn['customer_id'];
        $byCode = [];
        $cost = 0.0;
        foreach ($items as $it) {
            $code = self::REVENUE_MAP[$it['item_type']] ?? '4200';
            $byCode[$code] = ($byCode[$code] ?? 0) + (float) $it['line_subtotal'] - (float) $it['line_discount'];
            if ((int) $cn['restock'] === 1 && (int) ($it['track_stock'] ?? 0) === 1) {
                $cost += (float) $it['unit_cost'] * (float) $it['quantity'];
            }
        }
        $lines = [];
        foreach ($byCode as $code => $amt) {
            $lines[] = ['account' => (string) $code, 'debit' => round($amt, 2), 'customer_id' => $cid, 'description' => 'Credit note ' . $cn['number']];
        }
        $lines[] = ['account' => '2100', 'debit' => (float) $cn['vat_total'], 'description' => 'Output VAT reversed'];
        $diff = round((float) $cn['total'] - array_sum(array_column($lines, 'debit')), 2);
        if ($diff != 0 && count($lines) > 1) {
            $lines[0]['debit'] = round($lines[0]['debit'] + $diff, 2);
        }
        $lines[] = ['account' => '1100', 'credit' => $cn['total'], 'customer_id' => $cid, 'description' => 'Credit note ' . $cn['number']];
        if ($cost > 0) {
            $lines[] = ['account' => '1200', 'debit' => round($cost, 2), 'description' => 'Stock returned'];
            $lines[] = ['account' => '5000', 'credit' => round($cost, 2), 'description' => 'Cost of sales reversed'];
        }
        self::post('credit_note', $id, $cn['issue_date'], 'Credit note ' . $cn['number'], $lines, $cn['number'], $cn['branch_id'] ? (int) $cn['branch_id'] : null);
    }

    /** Supplier bill: Dr inventory (stock lines) or chosen expense account, Dr input VAT, Cr trade creditors. */
    public static function postBill(int $id): void
    {
        $db = Database::connect();
        $b  = $db->table('supplier_bills')->where('id', $id)->get()->getRowArray();
        if (!$b || $b['status'] === 'void' || $b['deleted_at']) {
            self::remove('bill', $id);
            return;
        }
        $items = $db->table('line_items li')->select('li.*, p.track_stock')->join('products p', 'p.id = li.product_id', 'left')
            ->where(['document_type' => 'bill', 'document_id' => $id])->get()->getResultArray();
        $sid = (int) $b['supplier_id'];
        $dr = [];
        foreach ($items as $it) {
            if ($it['account_id']) {
                $acc = (int) $it['account_id'];
            } elseif ((int) ($it['track_stock'] ?? 0) === 1) {
                $acc = self::accountId('1200');
            } else {
                $acc = self::accountId($it['item_type'] === 'sublet' ? '5100' : '5000');
            }
            $dr[$acc] = ($dr[$acc] ?? 0) + (float) $it['line_subtotal'] - (float) $it['line_discount'];
        }
        $lines = [];
        foreach ($dr as $acc => $amt) {
            $lines[] = ['account_id' => (int) $acc, 'debit' => round($amt, 2), 'supplier_id' => $sid];
        }
        $lines[] = ['account' => '1300', 'debit' => (float) $b['vat_total'], 'description' => 'Input VAT'];
        $diff = round((float) $b['total'] - array_sum(array_column($lines, 'debit')), 2);
        if ($diff != 0 && count($lines) > 1) {
            $lines[0]['debit'] = round($lines[0]['debit'] + $diff, 2);
        }
        $lines[] = ['account' => '2000', 'credit' => $b['total'], 'supplier_id' => $sid, 'description' => 'Bill ' . ($b['supplier_invoice'] ?: $b['number'])];
        self::post('bill', $id, $b['bill_date'], 'Supplier bill ' . $b['number'] . ($b['supplier_invoice'] ? ' (' . $b['supplier_invoice'] . ')' : ''), $lines, $b['supplier_invoice'] ?: $b['number'], $b['branch_id'] ? (int) $b['branch_id'] : null);
    }

    public static function postSupplierPayment(int $id): void
    {
        $db = Database::connect();
        $p  = $db->table('supplier_payments')->where('id', $id)->get()->getRowArray();
        if (!$p || $p['status'] !== 'completed') {
            self::remove('supplier_payment', $id);
            return;
        }
        self::post('supplier_payment', $id, substr($p['paid_at'], 0, 10), 'Supplier payment ' . $p['number'], [
            ['account' => '2000', 'debit' => $p['amount'], 'supplier_id' => (int) $p['supplier_id']],
            ['account_id' => (int) $p['paid_from_id'], 'credit' => $p['amount']],
        ], $p['reference'] ?: $p['number']);
    }

    /** Create a mirror-image journal that cancels an existing entry (audit-friendly undo). */
    public static function reverse(int $entryId, ?string $date = null): int
    {
        $db = Database::connect();
        $e  = $db->table('journal_entries')->where('id', $entryId)->get()->getRowArray();
        if (!$e || $e['reversed_by']) {
            throw new RuntimeException('Journal not found or already reversed.');
        }
        $lines = array_map(static fn ($l) => [
            'account_id' => (int) $l['account_id'], 'debit' => $l['credit'], 'credit' => $l['debit'],
            'description' => 'Reversal: ' . $l['description'], 'customer_id' => $l['customer_id'], 'supplier_id' => $l['supplier_id'],
        ], $db->table('journal_lines')->where('journal_entry_id', $entryId)->get()->getResultArray());
        $newId = (int) self::post('reversal', null, $date ?: date('Y-m-d'), 'Reversal of ' . $e['number'] . ': ' . $e['description'], $lines, $e['number']);
        $db->table('journal_entries')->where('id', $newId)->update(['is_reversal' => 1]);
        $db->table('journal_entries')->where('id', $entryId)->update(['reversed_by' => $newId]);
        return $newId;
    }

    /** Balance of one account (natural sign) as at a date, optionally from a date. */
    public static function balance(int $accountId, ?string $to = null, ?string $from = null): float
    {
        $db = Database::connect();
        $acc = $db->table('accounts')->select('type')->where('id', $accountId)->get()->getRowArray();
        $b = $db->table('journal_lines l')->select('COALESCE(SUM(l.debit),0) dr, COALESCE(SUM(l.credit),0) cr')
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->where('l.account_id', $accountId);
        if ($to) {
            $b->where('e.entry_date <=', $to);
        }
        if ($from) {
            $b->where('e.entry_date >=', $from);
        }
        $r = $b->get()->getRowArray();
        $net = (float) $r['dr'] - (float) $r['cr'];
        return round(in_array($acc['type'] ?? 'asset', ['asset', 'expense'], true) ? $net : -$net, 2);
    }
}
