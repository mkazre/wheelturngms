<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Ledger;
use App\Libraries\Purchasing;
use App\Libraries\Reports;
use App\Libraries\Settings as S;
use App\Libraries\VatReturns;

class Accounting extends BaseController
{
    public const TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];
    public const SUBTYPES = [
        'asset' => ['bank' => 'Bank', 'cash' => 'Cash', 'receivable' => 'Receivable', 'inventory' => 'Inventory', 'vat_input' => 'VAT input', 'current' => 'Other current asset', 'fixed_asset' => 'Fixed asset', 'contra_asset' => 'Accumulated depreciation'],
        'liability' => ['payable' => 'Payable', 'vat_output' => 'VAT output', 'vat_control' => 'VAT control', 'payroll' => 'Payroll', 'current' => 'Other current liability', 'non_current' => 'Long-term liability'],
        'equity' => ['capital' => 'Capital', 'retained' => 'Retained earnings', 'drawings' => 'Drawings'],
        'income' => ['sales' => 'Sales', 'other' => 'Other income'],
        'expense' => ['cost_of_sales' => 'Cost of sales', 'operating' => 'Operating expense', 'other' => 'Other expense'],
    ];

    /** Accounting hub: bank balances, AR/AP, VAT, profit YTD and shortcuts. */
    public function index()
    {
        $acc = fn ($code) => (int) ($this->db->table('accounts')->select('id')->where('code', $code)->get()->getRow()->id ?? 0);
        $banks = $this->db->table('accounts')->where('is_active', 1)->whereIn('sub_type', ['bank', 'cash'])->orderBy('code')->get()->getResultArray();
        foreach ($banks as &$b) {
            $b['balance'] = Ledger::balance((int) $b['id']);
            $b['unreconciled'] = $this->db->table('bank_transactions')->where(['account_id' => $b['id'], 'status' => 'unmatched'])->countAllResults();
        }
        unset($b);
        $fyStartMonth = (int) setting('financial_year_start', 3);
        $fyStart = date('Y') . '-' . str_pad((string) $fyStartMonth, 2, '0', STR_PAD_LEFT) . '-01';
        if ($fyStart > date('Y-m-d')) {
            $fyStart = date('Y-m-d', strtotime($fyStart . ' -1 year'));
        }
        $pl = Reports::summary($fyStart, date('Y-m-d'));
        return view('accounting/index', [
            'banks' => $banks,
            'ar' => Ledger::balance($acc('1100')),
            'ap' => Ledger::balance($acc('2000')),
            'vatOut' => Ledger::balance($acc('2100')), 'vatIn' => Ledger::balance($acc('1300')), 'vatControl' => Ledger::balance($acc('2110')),
            'stock' => Ledger::balance($acc('1200')),
            'fyStart' => $fyStart, 'pl' => $pl,
            'journals' => $this->db->table('journal_entries')->orderBy('id', 'DESC')->limit(8)->get()->getResultArray(),
            'lockDate' => setting('lock_date'),
            'aged' => array_slice(Purchasing::agedCreditors(), 0, 5),
        ]);
    }

    // ------------------------------------------------------------ chart of accounts

    public function accounts()
    {
        $rows = $this->db->table('accounts a')
            ->select('a.*, COALESCE(SUM(l.debit),0) dr, COALESCE(SUM(l.credit),0) cr, COUNT(l.id) lines')
            ->join('journal_lines l', 'l.account_id = a.id', 'left')
            ->groupBy('a.id, a.code, a.name, a.type, a.sub_type, a.description, a.is_system, a.is_active, a.created_at, a.updated_at, a.parent_id, a.bank_account_number')
            ->orderBy('a.code')->get()->getResultArray();
        foreach ($rows as &$r) {
            $net = (float) $r['dr'] - (float) $r['cr'];
            $r['balance'] = in_array($r['type'], ['asset', 'expense'], true) ? $net : -$net;
        }
        unset($r);
        return view('accounting/accounts', ['rows' => $rows, 'types' => self::TYPES, 'subtypes' => self::SUBTYPES]);
    }

    public function saveAccount()
    {
        $id = (int) $this->request->getPost('id');
        $data = $this->only(['code', 'name', 'type', 'sub_type', 'description', 'bank_account_number']) + ['is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now()];
        if (!$data['code'] || !$data['name'] || !isset(self::TYPES[$data['type']])) {
            return redirect()->back()->with('error', 'Code, name and type are required.');
        }
        $dupe = $this->db->table('accounts')->where('code', $data['code']);
        if ($id) {
            $dupe->where('id !=', $id);
        }
        if ($dupe->countAllResults()) {
            return redirect()->back()->with('error', 'Account code ' . $data['code'] . ' already exists.');
        }
        if ($id) {
            $acc = $this->db->table('accounts')->where('id', $id)->get()->getRowArray();
            if ((int) $acc['is_system'] === 1) {
                // System accounts: allow renaming only; code/type drive automatic postings
                $data = ['name' => $data['name'], 'description' => $data['description'], 'bank_account_number' => $data['bank_account_number'], 'updated_at' => $this->now()];
            }
            $this->db->table('accounts')->where('id', $id)->update($data);
        } else {
            $this->db->table('accounts')->insert($data + ['is_system' => 0, 'created_at' => $this->now()]);
        }
        Activity::log('account', $id ?: (int) $this->db->insertID(), 'saved', 'Account ' . $data['code'] . ' saved');
        return redirect()->to(site_url('accounting/accounts'))->with('success', 'Account saved.');
    }

    /** Account drill-down: every ledger line with running balance. */
    public function ledger(int $accountId)
    {
        $acc = $this->db->table('accounts')->where('id', $accountId)->get()->getRowArray() ?? $this->notFound('Account');
        [$from, $to] = $this->range('year');
        $opening = Ledger::balance($accountId, date('Y-m-d', strtotime($from . ' -1 day')));
        $lines = $this->db->table('journal_lines l')
            ->select('l.*, e.number, e.entry_date, e.description entry_desc, e.reference, e.source_type, e.source_id, c.first_name, c.last_name, c.company_name, c.type ctype, s.name supplier_name')
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->join('customers c', 'c.id = l.customer_id', 'left')->join('suppliers s', 's.id = l.supplier_id', 'left')
            ->where('l.account_id', $accountId)->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)
            ->orderBy('e.entry_date')->orderBy('e.id')->get()->getResultArray();
        $debitNormal = in_array($acc['type'], ['asset', 'expense'], true);
        $bal = $opening;
        foreach ($lines as &$l) {
            $bal += $debitNormal ? (float) $l['debit'] - (float) $l['credit'] : (float) $l['credit'] - (float) $l['debit'];
            $l['balance'] = round($bal, 2);
            $l['party'] = $l['supplier_name'] ?: (trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: ($l['company_name'] ?? ''));
        }
        unset($l);
        if (in_array($this->request->getGet('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportTable($this->request->getGet('export'), 'General Ledger ' . $acc['code'] . ' ' . $acc['name'], [
                'entry_date' => ['label' => 'Date'], 'number' => ['label' => 'Journal'], 'entry_desc' => ['label' => 'Description'], 'party' => ['label' => 'Customer / supplier'],
                'debit' => ['label' => 'Debit', 'type' => 'money'], 'credit' => ['label' => 'Credit', 'type' => 'money'], 'balance' => ['label' => 'Balance', 'type' => 'money'],
            ], $lines, ['Period' => fdate($from) . ' – ' . fdate($to), 'Opening balance' => money($opening)], ['entry_date' => 'Closing', 'debit' => array_sum(array_column($lines, 'debit')), 'credit' => array_sum(array_column($lines, 'credit')), 'balance' => $bal]);
        }
        return view('accounting/ledger', ['acc' => $acc, 'lines' => $lines, 'opening' => $opening, 'closing' => round($bal, 2), 'from' => $from, 'to' => $to,
            'accounts' => $this->db->table('accounts')->select('id, code, name')->orderBy('code')->get()->getResultArray()]);
    }

    // ------------------------------------------------------------ journals

    public function journals()
    {
        [$from, $to] = $this->range('month');
        $b = $this->db->table('journal_entries e')->select('e.*, u.first_name, (SELECT SUM(debit) FROM journal_lines WHERE journal_entry_id = e.id) amount')
            ->join('users u', 'u.id = e.created_by', 'left')->where('e.entry_date >=', $from)->where('e.entry_date <=', $to);
        if ($src = $this->request->getGet('source')) {
            $b->where('e.source_type', $src);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('e.number', $q)->orLike('e.description', $q)->orLike('e.reference', $q)->groupEnd();
        }
        [$rows, $pager] = $this->paginate($b->orderBy('e.entry_date', 'DESC')->orderBy('e.id', 'DESC'), 40);
        return view('accounting/journals', compact('rows', 'pager', 'from', 'to'));
    }

    public function journalForm()
    {
        return view('accounting/journal_form', [
            'accounts' => $this->db->table('accounts')->select('id, code, name, type')->where('is_active', 1)->orderBy('code')->get()->getResultArray(),
        ]);
    }

    public function journalSave()
    {
        $date = (string) $this->input('entry_date', date('Y-m-d'));
        $lines = [];
        foreach ((array) $this->request->getPost('lines') as $l) {
            $dr = round((float) ($l['debit'] ?? 0), 2);
            $cr = round((float) ($l['credit'] ?? 0), 2);
            if (empty($l['account_id']) || ($dr == 0.0 && $cr == 0.0)) {
                continue;
            }
            $lines[] = ['account_id' => (int) $l['account_id'], 'debit' => $dr, 'credit' => $cr, 'description' => mb_substr(trim((string) ($l['description'] ?? '')), 0, 255) ?: null];
        }
        if (count($lines) < 2) {
            return redirect()->back()->withInput()->with('error', 'A journal needs at least two lines.');
        }
        try {
            $id = Ledger::post('manual', null, $date, (string) $this->input('description', 'Manual journal'), $lines, $this->input('reference'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $this->db->table('journal_entries')->where('id', $id)->update(['memo' => $this->input('memo')]);
        $file = $this->request->getFile('document');
        if ($file && $file->isValid()) {
            try {
                Files::store($file, 'journal', (int) $id, 'document');
            } catch (\Throwable $e) {
            }
        }
        Activity::log('journal', (int) $id, 'created', 'Manual journal posted');
        return redirect()->to(site_url('accounting/journals/' . $id))->with('success', 'Journal posted.');
    }

    public function journal(int $id)
    {
        $e = $this->db->table('journal_entries e')->select('e.*, u.first_name, u.last_name')->join('users u', 'u.id = e.created_by', 'left')->where('e.id', $id)->get()->getRowArray() ?? $this->notFound('Journal');
        $lines = $this->db->table('journal_lines l')->select('l.*, a.code, a.name, c.first_name, c.last_name, c.company_name, c.type ctype, s.name supplier_name')
            ->join('accounts a', 'a.id = l.account_id')->join('customers c', 'c.id = l.customer_id', 'left')->join('suppliers s', 's.id = l.supplier_id', 'left')
            ->where('l.journal_entry_id', $id)->orderBy('l.id')->get()->getResultArray();
        $sourceUrl = match ($e['source_type']) {
            'invoice' => 'invoices/' . $e['source_id'], 'credit_note' => 'credit-notes/' . $e['source_id'], 'bill' => 'bills/' . $e['source_id'],
            'expense' => 'expenses/' . $e['source_id'] . '/edit', default => null,
        };
        return view('accounting/journal_show', ['e' => $e, 'lines' => $lines, 'sourceUrl' => $sourceUrl, 'files' => Files::for('journal', $id)]);
    }

    public function journalReverse(int $id)
    {
        $e = $this->db->table('journal_entries')->where('id', $id)->get()->getRowArray();
        if (!$e || !in_array($e['source_type'], ['manual', 'opening', 'bank'], true)) {
            return redirect()->back()->with('error', 'System journals are reversed by voiding their source document (invoice, bill, expense…).');
        }
        try {
            $new = Ledger::reverse($id, $this->input('date', date('Y-m-d')));
        } catch (\RuntimeException $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }
        return redirect()->to(site_url('accounting/journals/' . $new))->with('success', 'Reversing journal posted.');
    }

    // ------------------------------------------------------------ period lock

    public function lock()
    {
        $date = (string) $this->request->getPost('lock_date');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return redirect()->back()->with('error', 'Invalid date.');
        }
        if (!in_array(session('role'), ['admin', 'accountant'], true)) {
            return redirect()->back()->with('error', 'Only an administrator or accountant can change the lock date.');
        }
        S::set('lock_date', $date, 'finance');
        Activity::log('settings', null, 'lock_date', $date ? 'Books locked up to ' . $date : 'Period lock removed');
        return redirect()->back()->with('success', $date ? 'Books locked up to ' . fdate($date) . '.' : 'Period lock removed.');
    }

    // ------------------------------------------------------------ VAT returns

    public function vat()
    {
        $months = max(1, (int) setting('vat_period_months', 2));
        $rows = $this->db->table('vat_returns')->orderBy('period_start', 'DESC')->get()->getResultArray();
        $last = $rows[0]['period_end'] ?? null;
        $nextStart = $last ? date('Y-m-d', strtotime($last . ' +1 day')) : date('Y-m-01', strtotime('-' . ($months - 1) . ' months', strtotime(date('Y-m-01'))));
        $nextEnd = date('Y-m-t', strtotime($nextStart . ' +' . ($months - 1) . ' months'));
        $preview = VatReturns::calculate($nextStart, $nextEnd);
        return view('accounting/vat', [
            'rows' => $rows, 'nextStart' => $nextStart, 'nextEnd' => $nextEnd, 'preview' => $preview,
            'banks' => $this->db->table('accounts')->whereIn('sub_type', ['bank', 'cash'])->where('is_active', 1)->get()->getResultArray(),
        ]);
    }

    public function vatCreate()
    {
        $start = (string) $this->request->getPost('period_start');
        $end = (string) $this->request->getPost('period_end');
        if ($this->db->table('vat_returns')->where('period_start <=', $end)->where('period_end >=', $start)->countAllResults()) {
            return redirect()->back()->with('error', 'A VAT return already covers part of that period.');
        }
        try {
            $id = VatReturns::submit($start, $end, $this->input('sars_reference'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->to(site_url('accounting/vat'))->with('success', 'VAT return recorded. Output and input VAT moved to the VAT control account.');
    }

    public function vatPay(int $id)
    {
        try {
            VatReturns::pay($id, (int) $this->request->getPost('paid_from_id'), (string) $this->input('paid_at', date('Y-m-d')));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'VAT payment to SARS recorded.');
    }
}
