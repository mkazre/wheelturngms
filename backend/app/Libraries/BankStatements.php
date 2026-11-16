<?php

namespace App\Libraries;

use Config\Database;
use RuntimeException;

/**
 * Bank statement CSV import (FNB, ABSA, Standard Bank, Nedbank, Capitec, Investec…) and reconciliation.
 */
class BankStatements
{
    /** Read a CSV into rows, auto-detecting the delimiter. */
    public static function readCsv(string $path, int $max = 5000): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // strip BOM
        $firstLines = implode("\n", array_slice(preg_split('/\r\n|\r|\n/', $raw), 0, 20));
        $delim = substr_count($firstLines, ';') > substr_count($firstLines, ',') ? ';' : (substr_count($firstLines, "\t") > substr_count($firstLines, ',') ? "\t" : ',');
        $rows = [];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false && count($rows) < $max) {
            if ($r === [null]) {
                continue;
            }
            $rows[] = array_map(static fn ($c) => trim((string) $c), $r);
        }
        fclose($fh);
        return $rows;
    }

    public static function parseDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{8}$/', $v)) {
            return substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2);
        }
        foreach (['Y/m/d', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd M Y', 'j M Y', 'd F Y', 'Y/m/d H:i:s', 'Y-m-d H:i:s', 'd/m/y', 'd M y'] as $fmt) {
            $d = \DateTime::createFromFormat('!' . $fmt, $v);
            $err = \DateTime::getLastErrors();
            $clean = !$err || ((int) $err['warning_count'] === 0 && (int) $err['error_count'] === 0);
            if ($d && $clean && (int) $d->format('Y') > 1990) {
                return $d->format('Y-m-d');
            }
        }
        $ts = strtotime(str_replace('/', '-', $v));
        return $ts ? date('Y-m-d', $ts) : null;
    }

    public static function parseAmount(string $v): ?float
    {
        $v = str_replace(["\xc2\xa0", ' ', 'R', 'ZAR'], '', trim($v));
        if ($v === '' || $v === '-') {
            return null;
        }
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $v, $m)) {
            $neg = true;
            $v = $m[1];
        }
        if (preg_match('/(.*)(CR|DR|Cr|Dr)$/', $v, $m)) {
            $neg = strtoupper($m[2]) === 'DR';
            $v = $m[1];
        }
        if (substr_count($v, ',') === 1 && !str_contains($v, '.')) {
            $v = str_replace(',', '.', $v); // decimal comma
        }
        $v = str_replace(',', '', $v);
        if (!is_numeric($v)) {
            return null;
        }
        return round(($neg ? -1 : 1) * (float) $v, 2);
    }

    /**
     * Import mapped rows. $map = ['skip' => n, 'date' => col, 'desc' => col, 'ref' => col|null, 'amount' => col|null, 'debit' => col|null, 'credit' => col|null, 'balance' => col|null]
     * Returns [imported, duplicates, errors].
     */
    public static function import(int $accountId, array $rows, array $map): array
    {
        $db = Database::connect();
        $batch = date('YmdHis');
        $imported = $dupes = $errors = 0;
        foreach (array_slice($rows, (int) $map['skip']) as $i => $r) {
            $date = self::parseDate($r[$map['date']] ?? '');
            if ($map['amount'] !== '' && $map['amount'] !== null) {
                $amount = self::parseAmount($r[$map['amount']] ?? '');
            } else {
                $dr = self::parseAmount($r[$map['debit']] ?? '') ?? 0;
                $cr = self::parseAmount($r[$map['credit']] ?? '') ?? 0;
                $amount = round(abs($cr) - abs($dr), 2);
            }
            if (!$date || $amount === null || $amount == 0.0) {
                $errors++;
                continue;
            }
            $desc = mb_substr(trim(($r[$map['desc']] ?? '')), 0, 255);
            $ref = ($map['ref'] ?? '') !== '' ? mb_substr((string) ($r[$map['ref']] ?? ''), 0, 100) : null;
            $balance = ($map['balance'] ?? '') !== '' ? self::parseAmount($r[$map['balance']] ?? '') : null;
            $fp = hash('sha256', $accountId . '|' . $date . '|' . $amount . '|' . $desc . '|' . $balance . '|' . ($balance === null ? $i : ''));
            if ($db->table('bank_transactions')->where('fingerprint', $fp)->countAllResults()) {
                $dupes++;
                continue;
            }
            $db->table('bank_transactions')->insert([
                'account_id' => $accountId, 'txn_date' => $date, 'description' => $desc, 'reference' => $ref, 'amount' => $amount, 'running_balance' => $balance,
                'import_batch' => $batch, 'fingerprint' => $fp, 'status' => 'unmatched', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $imported++;
        }
        return [$imported, $dupes, $errors];
    }

    /** Ledger lines on this bank account that could match a statement line. */
    public static function candidates(array $txn, int $days = 10): array
    {
        $amt = (float) $txn['amount'];
        return Database::connect()->table('journal_lines l')
            ->select('l.id line_id, l.journal_entry_id, l.debit, l.credit, e.number, e.entry_date, e.description, e.reference')
            ->join('journal_entries e', 'e.id = l.journal_entry_id')
            ->where('l.account_id', $txn['account_id'])->where('l.reconciled', 0)
            ->where('e.entry_date >=', date('Y-m-d', strtotime($txn['txn_date'] . " -{$days} days")))
            ->where('e.entry_date <=', date('Y-m-d', strtotime($txn['txn_date'] . " +{$days} days")))
            ->where($amt > 0 ? 'l.debit' : 'l.credit', abs($amt))
            ->orderBy('e.entry_date')->limit(10)->get()->getResultArray();
    }

    public static function match(int $txnId, int $lineId): void
    {
        $db = Database::connect();
        $line = $db->table('journal_lines')->where('id', $lineId)->get()->getRowArray();
        $db->transStart();
        $db->table('journal_lines')->where('id', $lineId)->update(['reconciled' => 1, 'bank_txn_id' => $txnId]);
        $db->table('bank_transactions')->where('id', $txnId)->update(['status' => 'matched', 'journal_entry_id' => $line['journal_entry_id'], 'matched_by' => session('user_id'), 'matched_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();
    }

    public static function unmatch(int $txnId): void
    {
        $db = Database::connect();
        $db->table('journal_lines')->where('bank_txn_id', $txnId)->update(['reconciled' => 0, 'bank_txn_id' => null]);
        $db->table('bank_transactions')->where('id', $txnId)->update(['status' => 'unmatched', 'journal_entry_id' => null, 'matched_at' => null]);
    }

    /** Auto-match where exactly one ledger line has the same amount within the date window. */
    public static function autoMatch(int $accountId): int
    {
        $db = Database::connect();
        $n = 0;
        foreach ($db->table('bank_transactions')->where(['account_id' => $accountId, 'status' => 'unmatched'])->orderBy('txn_date')->get()->getResultArray() as $t) {
            $c = self::candidates($t, 5);
            if (count($c) === 1) {
                self::match((int) $t['id'], (int) $c[0]['line_id']);
                $n++;
            }
        }
        return $n;
    }

    /**
     * Create the missing transaction directly from a statement line (bank fees, debit orders, deposits…)
     * and reconcile it immediately.
     */
    public static function createFromLine(int $txnId, int $contraAccountId, bool $includesVat, ?string $description = null): int
    {
        $db = Database::connect();
        $t = $db->table('bank_transactions')->where('id', $txnId)->get()->getRowArray();
        if (!$t || $t['status'] !== 'unmatched') {
            throw new RuntimeException('Statement line already reconciled.');
        }
        $amt = abs((float) $t['amount']);
        $vat = $includesVat ? round($amt * 15 / 115, 2) : 0;
        $desc = $description ?: $t['description'];
        if ((float) $t['amount'] < 0) {
            $lines = [['account_id' => $contraAccountId, 'debit' => $amt - $vat, 'description' => $desc]];
            if ($vat > 0) {
                $lines[] = ['account' => '1300', 'debit' => $vat, 'description' => 'Input VAT'];
            }
            $lines[] = ['account_id' => (int) $t['account_id'], 'credit' => $amt, 'description' => $desc];
        } else {
            $lines = [['account_id' => (int) $t['account_id'], 'debit' => $amt, 'description' => $desc]];
            if ($vat > 0) {
                $lines[] = ['account' => '2100', 'credit' => $vat, 'description' => 'Output VAT'];
            }
            $lines[] = ['account_id' => $contraAccountId, 'credit' => $amt - $vat, 'description' => $desc];
        }
        $db->transStart();
        $entryId = (int) Ledger::post('bank', $txnId, $t['txn_date'], 'Bank: ' . $desc, $lines, $t['reference']);
        $line = $db->table('journal_lines')->where(['journal_entry_id' => $entryId, 'account_id' => $t['account_id']])->get()->getRowArray();
        self::match($txnId, (int) $line['id']);
        $db->transComplete();
        return $entryId;
    }
}
