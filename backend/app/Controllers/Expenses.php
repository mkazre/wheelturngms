<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Ledger;
use App\Libraries\Numbering;

class Expenses extends BaseController
{
    private function accounts(): array
    {
        return $this->db->table('accounts')->where('is_active', 1)->whereIn('type', ['expense', 'asset'])
            ->whereNotIn('sub_type', ['bank', 'cash', 'receivable', 'vat_input', 'contra_asset'])->orderBy('code')->get()->getResultArray();
    }

    private function bankAccounts(): array
    {
        return $this->db->table('accounts')->where('is_active', 1)->groupStart()->whereIn('sub_type', ['bank', 'cash'])->orWhere('code', '2000')->groupEnd()->orderBy('code')->get()->getResultArray();
    }

    private function query()
    {
        [$from, $to] = $this->range('month');
        $b = $this->db->table('expenses e')
            ->select('e.*, a.code account_code, a.name account_name, s.name supplier_name')
            ->join('accounts a', 'a.id = e.account_id')->join('suppliers s', 's.id = e.supplier_id', 'left')
            ->where('e.deleted_at', null)->where('e.expense_date >=', $from)->where('e.expense_date <=', $to);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('e.description', $q)->orLike('e.payee', $q)->orLike('e.reference', $q)->orLike('e.number', $q)->orLike('s.name', $q)->groupEnd();
        }
        if ($acc = (int) $this->request->getGet('account_id')) {
            $b->where('e.account_id', $acc);
        }
        return [$b->orderBy('e.expense_date', 'DESC')->orderBy('e.id', 'DESC'), $from, $to];
    }

    public function index()
    {
        [$b, $from, $to] = $this->query();
        $all = (clone $b)->get()->getResultArray();
        [$rows, $pager] = $this->paginate($b, 30);
        $byAccount = [];
        foreach ($all as $e) {
            $byAccount[$e['account_name']] = ($byAccount[$e['account_name']] ?? 0) + (float) $e['amount_excl'];
        }
        arsort($byAccount);
        return view('expenses/index', [
            'rows' => $rows, 'pager' => $pager, 'from' => $from, 'to' => $to, 'accounts' => $this->accounts(),
            'totals' => ['excl' => array_sum(array_column($all, 'amount_excl')), 'vat' => array_sum(array_column($all, 'vat_amount')), 'total' => array_sum(array_column($all, 'total')), 'count' => count($all)],
            'byAccount' => $byAccount, 'slips' => Files::counts('expense', array_column($rows, 'id')),
        ]);
    }

    public function export(string $format)
    {
        [$b, $from, $to] = $this->query();
        $rows = array_map(static fn ($r) => $r + ['account' => $r['account_code'] . ' ' . $r['account_name'], 'paid_to' => $r['supplier_name'] ?: $r['payee']], $b->get()->getResultArray());
        $sum = static fn ($k) => array_sum(array_column($rows, $k));
        return $this->exportTable($format, 'Expenses', [
            'number' => ['label' => 'No.'], 'expense_date' => ['label' => 'Date'], 'paid_to' => ['label' => 'Paid to'], 'description' => ['label' => 'Description'],
            'account' => ['label' => 'Account'], 'reference' => ['label' => 'Ref.'], 'amount_excl' => ['label' => 'Excl. VAT', 'type' => 'money'],
            'vat_amount' => ['label' => 'VAT', 'type' => 'money'], 'total' => ['label' => 'Total', 'type' => 'money'],
        ], $rows, ['Period' => fdate($from) . ' – ' . fdate($to)], ['number' => 'Totals', 'amount_excl' => $sum('amount_excl'), 'vat_amount' => $sum('vat_amount'), 'total' => $sum('total')]);
    }

    public function form(?int $id = null)
    {
        $e = $id ? $this->db->table('expenses')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : ['expense_date' => date('Y-m-d'), 'is_vat_claimable' => 1, 'payment_method' => 'eft'];
        if ($id && !$e) {
            return $this->notFound('Expense');
        }
        return view('expenses/form', [
            'e' => $e, 'accounts' => $this->accounts(), 'banks' => $this->bankAccounts(),
            'suppliers' => $this->db->table('suppliers')->select('id, name')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'files' => $id ? Files::for('expense', $id) : [],
        ]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['expense_date' => 'required|valid_date[Y-m-d]', 'account_id' => 'required|is_natural_no_zero', 'total' => 'required|decimal|greater_than[0]', 'description' => 'required|max_length[500]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $total = round((float) $this->input('total'), 2);
        $claim = $this->request->getPost('is_vat_claimable') ? 1 : 0;
        $rate = (float) setting('vat_rate', 15);
        $vat = $claim && (int) setting('vat_registered', 1) === 1 ? round($total * $rate / (100 + $rate), 2) : 0;
        if ($this->input('vat_amount') !== null && $claim) {
            $vat = round((float) $this->input('vat_amount'), 2); // allow override from the slip
        }
        $data = $this->only(['expense_date', 'supplier_id', 'payee', 'account_id', 'paid_from_id', 'description', 'payment_method', 'reference']) + [
            'total' => $total, 'vat_amount' => $vat, 'amount_excl' => round($total - $vat, 2), 'is_vat_claimable' => $claim, 'updated_at' => $this->now(),
        ];
        $this->db->transStart();
        if ($id) {
            $this->db->table('expenses')->where('id', $id)->update($data);
        } else {
            $data += ['number' => Numbering::next('expense'), 'branch_id' => session('branch_id'), 'created_by' => session('user_id'), 'created_at' => $this->now()];
            $this->db->table('expenses')->insert($data);
            $id = (int) $this->db->insertID();
        }
        Ledger::postExpense($id);
        $this->db->transComplete();

        $slip = $this->request->getFile('slip');
        if ($slip && $slip->isValid()) {
            try {
                Files::store($slip, 'expense', $id, 'receipt', $data['description']);
            } catch (\Throwable $e) {
                return redirect()->to(site_url('expenses'))->with('warning', 'Expense saved, but the slip upload failed: ' . $e->getMessage());
            }
        }
        Activity::log('expense', $id, 'saved', 'Expense ' . money($total) . ': ' . $data['description']);
        return redirect()->to(site_url($this->request->getPost('another') ? 'expenses/new' : 'expenses'))->with('success', 'Expense saved and posted to the ledger.');
    }

    public function delete(int $id)
    {
        $this->db->table('expenses')->where('id', $id)->update(['deleted_at' => $this->now()]);
        Ledger::postExpense($id); // removes the journal
        Activity::log('expense', $id, 'deleted', 'Expense deleted');
        return redirect()->to(site_url('expenses'))->with('success', 'Expense deleted and reversed.');
    }
}
