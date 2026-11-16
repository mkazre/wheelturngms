<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Ledger;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Purchasing;

/** Supplier bills (accounts payable) and supplier payments. */
class Bills extends BaseController
{
    private function expenseAccounts(): array
    {
        return $this->db->table('accounts')->where('is_active', 1)->groupStart()->where('type', 'expense')->orWhereIn('sub_type', ['fixed_asset', 'inventory'])->groupEnd()->orderBy('code')->get()->getResultArray();
    }

    private function bankAccounts(): array
    {
        return $this->db->table('accounts')->where('is_active', 1)->whereIn('sub_type', ['bank', 'cash'])->orderBy('code')->get()->getResultArray();
    }

    private function query()
    {
        $b = $this->db->table('supplier_bills b')->select('b.*, s.name supplier_name')->join('suppliers s', 's.id = b.supplier_id')->where('b.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('b.number', $q)->orLike('b.supplier_invoice', $q)->orLike('s.name', $q)->groupEnd();
        }
        $s = $this->request->getGet('status');
        if ($s === 'outstanding') {
            $b->whereIn('b.status', ['unpaid', 'partial']);
        } elseif ($s === 'overdue') {
            $b->whereIn('b.status', ['unpaid', 'partial'])->where('b.due_date <', date('Y-m-d'));
        } elseif ($s) {
            $b->where('b.status', $s);
        }
        return $b->orderBy('b.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $t = $this->db->table('supplier_bills')->where('deleted_at', null)->whereIn('status', ['unpaid', 'partial']);
        $stats = [
            'owing' => (float) (clone $t)->selectSum('balance_due')->get()->getRow()->balance_due,
            'overdue' => (float) (clone $t)->selectSum('balance_due')->where('due_date <', date('Y-m-d'))->get()->getRow()->balance_due,
            'week' => (float) (clone $t)->selectSum('balance_due')->where('due_date <=', date('Y-m-d', strtotime('+7 days')))->get()->getRow()->balance_due,
            'paid_month' => (float) $this->db->table('supplier_payments')->selectSum('amount')->where('status', 'completed')->where('paid_at >=', date('Y-m-01'))->get()->getRow()->amount,
        ];
        return view('purchases/bill_index', compact('rows', 'pager', 'stats'));
    }

    public function export(string $format)
    {
        $rows = $this->query()->get()->getResultArray();
        $sum = static fn ($k) => array_sum(array_column($rows, $k));
        return $this->exportTable($format, 'Supplier Bills', [
            'number' => ['label' => 'Bill'], 'supplier_invoice' => ['label' => 'Supplier inv.'], 'bill_date' => ['label' => 'Date'], 'due_date' => ['label' => 'Due'],
            'supplier_name' => ['label' => 'Supplier'], 'status' => ['label' => 'Status'], 'vat_total' => ['label' => 'VAT', 'type' => 'money'],
            'total' => ['label' => 'Total', 'type' => 'money'], 'balance_due' => ['label' => 'Balance', 'type' => 'money'],
        ], $rows, [], ['number' => 'Totals', 'vat_total' => $sum('vat_total'), 'total' => $sum('total'), 'balance_due' => $sum('balance_due')]);
    }

    public function form(?int $id = null)
    {
        $bill = $id ? $this->db->table('supplier_bills')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : null;
        if ($id && !$bill) {
            return $this->notFound('Bill');
        }
        if ($bill && ((float) $bill['amount_paid'] > 0 || $bill['status'] === 'void')) {
            return redirect()->to(site_url('bills/' . $id))->with('error', 'Bills with payments cannot be edited.');
        }
        return view('purchases/bill_form', [
            'bill' => $bill ?? ['bill_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')), 'supplier_id' => $this->request->getGet('supplier_id')],
            'lines' => $id ? LineItems::get('bill', $id) : [],
            'suppliers' => $this->db->table('suppliers')->select('id, name, payment_terms_days')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'accounts' => $this->expenseAccounts(),
        ]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['supplier_id' => 'required|is_natural_no_zero', 'bill_date' => 'required|valid_date[Y-m-d]', 'due_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['supplier_id', 'supplier_invoice', 'bill_date', 'due_date', 'notes']) + ['updated_at' => $this->now()];
        try {
            Ledger::assertOpen($data['bill_date']);
            $this->db->transStart();
            if ($id) {
                Purchasing::reverseStock($id);
                $this->db->table('supplier_bills')->where('id', $id)->update($data);
            } else {
                $this->db->table('supplier_bills')->insert($data + ['number' => Numbering::next('bill'), 'branch_id' => session('branch_id'), 'status' => 'unpaid', 'created_by' => session('user_id'), 'created_at' => $this->now()]);
                $id = (int) $this->db->insertID();
            }
            LineItems::sync('bill', $id, (array) $this->request->getPost('items'));
            Purchasing::receiveStock($id);
            Purchasing::refreshBill($id);
            Ledger::postBill($id);
            $this->db->transComplete();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $file = $this->request->getFile('document');
        if ($file && $file->isValid()) {
            try {
                Files::store($file, 'bill', $id, 'document', 'Supplier invoice ' . $data['supplier_invoice']);
            } catch (\Throwable $e) {
                return redirect()->to(site_url('bills/' . $id))->with('warning', 'Bill saved, but the document upload failed: ' . $e->getMessage());
            }
        }
        Activity::log('bill', $id, 'saved', 'Supplier bill saved');
        return redirect()->to(site_url('bills/' . $id))->with('success', 'Bill saved. Stock, payables and input VAT posted.');
    }

    public function show(int $id)
    {
        $bill = $this->db->table('supplier_bills')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() ?? $this->notFound('Bill');
        return view('purchases/bill_show', [
            'bill' => $bill, 'items' => LineItems::get('bill', $id),
            'supplier' => $this->db->table('suppliers')->where('id', $bill['supplier_id'])->get()->getRowArray(),
            'payments' => $this->db->table('supplier_payments p')->select('p.*, a.name account_name')->join('accounts a', 'a.id = p.paid_from_id')->where('p.bill_id', $id)->orderBy('p.paid_at')->get()->getResultArray(),
            'po' => $bill['purchase_order_id'] ? $this->db->table('purchase_orders')->select('id, number')->where('id', $bill['purchase_order_id'])->get()->getRowArray() : null,
            'banks' => $this->bankAccounts(), 'files' => Files::for('bill', $id), 'activity' => Activity::for('bill', $id),
        ]);
    }

    public function pay(int $id)
    {
        $bill = $this->db->table('supplier_bills')->where('id', $id)->get()->getRowArray();
        $amount = round((float) $this->request->getPost('amount'), 2);
        if (!$bill || in_array($bill['status'], ['void', 'paid'], true) || $amount <= 0 || $amount - (float) $bill['balance_due'] > 0.009) {
            return redirect()->back()->with('error', 'Enter an amount up to the balance due (' . money($bill['balance_due'] ?? 0) . ').');
        }
        $paidAt = $this->input('paid_at') ? date('Y-m-d H:i:s', strtotime($this->input('paid_at'))) : $this->now();
        try {
            Purchasing::pay($id, $amount, (int) $this->request->getPost('paid_from_id'), (string) $this->input('method', 'eft'), $this->input('reference'), $paidAt);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Supplier payment of ' . money($amount) . ' recorded.');
    }

    public function void(int $id)
    {
        $bill = $this->db->table('supplier_bills')->where('id', $id)->get()->getRowArray();
        if ((float) $bill['amount_paid'] > 0) {
            return redirect()->back()->with('error', 'This bill has payments. Reverse them first.');
        }
        try {
            $this->db->transStart();
            $this->db->table('supplier_bills')->where('id', $id)->update(['status' => 'void', 'balance_due' => 0, 'updated_at' => $this->now()]);
            Purchasing::reverseStock($id);
            Ledger::postBill($id);
            if ($bill['purchase_order_id']) {
                $this->db->table('purchase_orders')->where('id', $bill['purchase_order_id'])->update(['status' => 'sent']);
            }
            $this->db->transComplete();
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        Activity::log('bill', $id, 'void', 'Bill voided');
        return redirect()->back()->with('success', 'Bill voided. Stock and ledger reversed.');
    }
}
