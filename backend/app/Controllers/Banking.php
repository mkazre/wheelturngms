<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\BankStatements;
use App\Libraries\Ledger;

class Banking extends BaseController
{
    private function bankAccount(int $id): array
    {
        $a = $this->db->table('accounts')->where('id', $id)->whereIn('sub_type', ['bank', 'cash'])->get()->getRowArray();
        if (!$a) {
            $this->notFound('Bank account');
        }
        return $a;
    }

    public function show(int $id)
    {
        $acc = $this->bankAccount($id);
        $status = $this->request->getGet('status') ?: 'unmatched';
        $b = $this->db->table('bank_transactions t')->select('t.*, e.number journal_number')->join('journal_entries e', 'e.id = t.journal_entry_id', 'left')->where('t.account_id', $id);
        if ($status !== 'all') {
            $b->where('t.status', $status);
        }
        [$rows, $pager] = $this->paginate($b->orderBy('t.txn_date', 'DESC')->orderBy('t.id', 'DESC'), 40);
        foreach ($rows as &$r) {
            $r['candidates'] = $r['status'] === 'unmatched' ? BankStatements::candidates($r) : [];
        }
        unset($r);
        $lastStatement = $this->db->table('bank_transactions')->where('account_id', $id)->where('running_balance IS NOT NULL')->orderBy('txn_date', 'DESC')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        $counts = array_column($this->db->table('bank_transactions')->select('status, COUNT(*) n')->where('account_id', $id)->groupBy('status')->get()->getResultArray(), 'n', 'status');
        $unreconciledLedger = $this->db->table('journal_lines l')->select('COALESCE(SUM(l.debit - l.credit),0) v', false)->where(['l.account_id' => $id, 'l.reconciled' => 0])->get()->getRow()->v;
        return view('banking/show', [
            'acc' => $acc, 'rows' => $rows, 'pager' => $pager, 'status' => $status, 'counts' => $counts,
            'ledgerBalance' => Ledger::balance($id), 'lastStatement' => $lastStatement, 'unreconciledLedger' => (float) $unreconciledLedger,
            'contraAccounts' => $this->db->table('accounts')->select('id, code, name, type')->where('is_active', 1)->whereNotIn('sub_type', ['bank', 'cash'])->orderBy('code')->get()->getResultArray(),
        ]);
    }

    /** Step 1: upload a CSV and show a mapping preview. */
    public function upload(int $id)
    {
        $acc = $this->bankAccount($id);
        $file = $this->request->getFile('statement');
        if (!$file || !$file->isValid() || !in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'Upload your bank statement as a CSV file (download it from your online banking).');
        }
        $name = 'stmt_' . bin2hex(random_bytes(8)) . '.csv';
        $file->move(WRITEPATH . 'uploads/statements', $name);
        $rows = BankStatements::readCsv(WRITEPATH . 'uploads/statements/' . $name, 40);
        // Guess mapping: header row is the first row containing a "date"-like cell
        $guess = ['skip' => 0, 'date' => 0, 'desc' => 1, 'ref' => '', 'amount' => 2, 'debit' => '', 'credit' => '', 'balance' => ''];
        foreach ($rows as $i => $r) {
            $lower = array_map('strtolower', $r);
            if (preg_grep('/date/', $lower)) {
                $guess['skip'] = $i + 1;
                foreach ($lower as $c => $h) {
                    match (true) {
                        str_contains($h, 'date') => $guess['date'] = $c,
                        str_contains($h, 'desc') || str_contains($h, 'narrative') || str_contains($h, 'detail') => $guess['desc'] = $c,
                        str_contains($h, 'ref') => $guess['ref'] = $c,
                        str_contains($h, 'balance') => $guess['balance'] = $c,
                        str_contains($h, 'debit') => $guess['debit'] = $c,
                        str_contains($h, 'credit') => $guess['credit'] = $c,
                        str_contains($h, 'amount') => $guess['amount'] = $c,
                        default => null,
                    };
                }
                if ($guess['debit'] !== '' && $guess['credit'] !== '') {
                    $guess['amount'] = '';
                }
                break;
            }
        }
        return view('banking/map', ['acc' => $acc, 'rows' => $rows, 'file' => $name, 'guess' => $guess, 'cols' => max(array_map('count', $rows ?: [[]]))]);
    }

    /** Step 2: import using the chosen mapping, then auto-match. */
    public function import(int $id)
    {
        $acc = $this->bankAccount($id);
        $file = basename((string) $this->request->getPost('file'));
        $path = WRITEPATH . 'uploads/statements/' . $file;
        if (!preg_match('/^stmt_[a-f0-9]{16}\.csv$/', $file) || !is_file($path)) {
            return redirect()->to(site_url('banking/' . $id))->with('error', 'Upload expired. Please upload the statement again.');
        }
        $map = [];
        foreach (['skip', 'date', 'desc', 'ref', 'amount', 'debit', 'credit', 'balance'] as $k) {
            $v = $this->request->getPost($k);
            $map[$k] = $v === '' || $v === null ? '' : (int) $v;
        }
        if ($map['amount'] === '' && ($map['debit'] === '' || $map['credit'] === '')) {
            return redirect()->back()->with('error', 'Choose an Amount column, or both Debit and Credit columns.');
        }
        [$imported, $dupes, $errors] = BankStatements::import($id, BankStatements::readCsv($path), $map);
        @unlink($path);
        $matched = BankStatements::autoMatch($id);
        Activity::log('bank', $id, 'import', "Imported {$imported} statement lines into {$acc['name']}");
        return redirect()->to(site_url('banking/' . $id))->with('success', "Imported {$imported} lines ({$dupes} duplicates skipped, {$errors} unreadable). Auto-matched {$matched}.");
    }

    public function autoMatch(int $id)
    {
        $this->bankAccount($id);
        $n = BankStatements::autoMatch($id);
        return redirect()->back()->with('success', "Auto-matched {$n} transactions.");
    }

    public function match(int $txnId)
    {
        BankStatements::match($txnId, (int) $this->request->getPost('line_id'));
        return redirect()->back()->with('success', 'Matched and reconciled.');
    }

    public function create(int $txnId)
    {
        try {
            BankStatements::createFromLine($txnId, (int) $this->request->getPost('account_id'), (bool) $this->request->getPost('includes_vat'), $this->input('description'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Transaction created and reconciled.');
    }

    public function ignore(int $txnId)
    {
        $this->db->table('bank_transactions')->where('id', $txnId)->update(['status' => 'ignored', 'matched_by' => session('user_id'), 'matched_at' => $this->now()]);
        return redirect()->back()->with('success', 'Statement line ignored.');
    }

    public function unmatch(int $txnId)
    {
        BankStatements::unmatch($txnId);
        $this->db->table('bank_transactions')->where('id', $txnId)->where('status', 'ignored')->update(['status' => 'unmatched']);
        return redirect()->back()->with('success', 'Statement line un-reconciled.');
    }
}
