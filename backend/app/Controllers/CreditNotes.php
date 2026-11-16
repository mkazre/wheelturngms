<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Billing;
use App\Libraries\Ledger;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Pdf;

/**
 * SARS-compliant credit notes against tax invoices (returns, overcharges, goodwill).
 */
class CreditNotes extends BaseController
{
    private function query()
    {
        $b = $this->db->table('credit_notes cn')->select('cn.*, i.number invoice_number, c.first_name, c.last_name, c.company_name, c.type')
            ->join('invoices i', 'i.id = cn.invoice_id', 'left')->join('customers c', 'c.id = cn.customer_id')->where('cn.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('cn.number', $q)->orLike('i.number', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->groupEnd();
        }
        return $b->orderBy('cn.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        return view('credit_notes/index', compact('rows', 'pager'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['customer' => customer_name($r)], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Credit Notes', [
            'number' => ['label' => 'Credit note'], 'issue_date' => ['label' => 'Date'], 'invoice_number' => ['label' => 'Invoice'], 'customer' => ['label' => 'Customer'],
            'reason' => ['label' => 'Reason'], 'status' => ['label' => 'Status'], 'vat_total' => ['label' => 'VAT', 'type' => 'money'], 'total' => ['label' => 'Total', 'type' => 'money'],
        ], $rows, [], ['number' => 'Total', 'total' => array_sum(array_column($rows, 'total'))]);
    }

    /** New credit note, pre-filled from an invoice (?invoice_id=). */
    public function form()
    {
        $invId = (int) $this->request->getGet('invoice_id');
        $inv = $this->db->table('invoices')->where(['id' => $invId, 'deleted_at' => null])->get()->getRowArray();
        if (!$inv || in_array($inv['status'], ['draft', 'void'], true)) {
            return redirect()->to(site_url('invoices'))->with('error', 'Open an issued invoice and click "Credit note".');
        }
        $already = (float) $inv['amount_credited'];
        return view('credit_notes/form', [
            'inv' => $inv, 'customer' => $this->db->table('customers')->where('id', $inv['customer_id'])->get()->getRowArray(),
            'lines' => LineItems::get('invoice', $invId), 'creditable' => round((float) $inv['total'] - $already, 2),
        ]);
    }

    public function save()
    {
        $invId = (int) $this->request->getPost('invoice_id');
        $inv = $this->db->table('invoices')->where('id', $invId)->get()->getRowArray();
        if (!$inv || in_array($inv['status'], ['draft', 'void'], true)) {
            return redirect()->back()->with('error', 'Invalid invoice.');
        }
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '') {
            return redirect()->back()->withInput()->with('error', 'A reason is required on a credit note (SARS).');
        }
        $items = array_values(array_filter((array) $this->request->getPost('items'), static fn ($l) => (float) ($l['quantity'] ?? 0) > 0 && !empty($l['include'])));
        if (!$items) {
            return redirect()->back()->withInput()->with('error', 'Select at least one line to credit.');
        }
        $date = $this->input('issue_date', date('Y-m-d'));
        try {
            Ledger::assertOpen($date);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $this->db->transStart();
        $this->db->table('credit_notes')->insert([
            'number' => Numbering::next('credit_note'), 'branch_id' => $inv['branch_id'], 'invoice_id' => $invId, 'customer_id' => $inv['customer_id'],
            'issue_date' => $date, 'reason' => mb_substr($reason, 0, 500), 'restock' => $this->request->getPost('restock') ? 1 : 0,
            'status' => 'issued', 'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        $id = (int) $this->db->insertID();
        $totals = LineItems::sync('credit_note', $id, $items);
        $creditable = round((float) $inv['total'] - (float) $inv['amount_credited'], 2);
        if ($totals['total'] - $creditable > 0.009) {
            $this->db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Credit (' . money($totals['total']) . ') exceeds what is left to credit on this invoice (' . money($creditable) . ').');
        }
        if ((int) $this->request->getPost('restock') === 1) {
            foreach ($this->db->table('line_items li')->select('li.product_id, li.quantity, li.unit_cost')->join('products p', 'p.id = li.product_id')
                ->where(['li.document_type' => 'credit_note', 'li.document_id' => $id, 'p.track_stock' => 1])->get()->getResultArray() as $l) {
                $this->db->table('products')->set('qty_on_hand', 'qty_on_hand + ' . (float) $l['quantity'], false)->where('id', $l['product_id'])->update();
                $this->db->table('stock_movements')->insert(['product_id' => $l['product_id'], 'type' => 'in', 'quantity' => (float) $l['quantity'], 'unit_cost' => $l['unit_cost'], 'reference_type' => 'credit_note', 'reference_id' => $id, 'note' => 'Customer return', 'user_id' => session('user_id'), 'created_at' => $this->now()]);
            }
        }
        Ledger::postCreditNote($id);
        Billing::refreshInvoiceStatus($invId);
        $this->db->transComplete();
        Activity::log('invoice', $invId, 'credit_note', 'Credit note issued for ' . money($totals['total']) . ': ' . $reason);
        return redirect()->to(site_url('credit-notes/' . $id))->with('success', 'Credit note issued. Invoice balance and VAT adjusted.');
    }

    public function show(int $id)
    {
        $cn = $this->db->table('credit_notes')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() ?? $this->notFound('Credit note');
        return view('credit_notes/show', $this->docData($cn));
    }

    private function docData(array $cn): array
    {
        return [
            'cn' => $cn, 'items' => LineItems::get('credit_note', (int) $cn['id']),
            'invoice' => $this->db->table('invoices')->where('id', $cn['invoice_id'])->get()->getRowArray(),
            'customer' => $this->db->table('customers')->where('id', $cn['customer_id'])->get()->getRowArray(),
        ];
    }

    public function pdf(int $id)
    {
        $cn = $this->db->table('credit_notes')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Credit note');
        return Pdf::respond(Pdf::render('pdf/credit_note', $this->docData($cn)), $cn['number'] . '.pdf');
    }

    public function void(int $id)
    {
        $cn = $this->db->table('credit_notes')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Credit note');
        try {
            Ledger::assertOpen($cn['issue_date']);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $this->db->transStart();
        $this->db->table('credit_notes')->where('id', $id)->update(['status' => 'void', 'updated_at' => $this->now()]);
        foreach ($this->db->table('stock_movements')->where(['reference_type' => 'credit_note', 'reference_id' => $id])->get()->getResultArray() as $m) {
            $this->db->table('products')->set('qty_on_hand', 'qty_on_hand - ' . (float) $m['quantity'], false)->where('id', $m['product_id'])->update();
        }
        $this->db->table('stock_movements')->where(['reference_type' => 'credit_note', 'reference_id' => $id])->delete();
        Ledger::postCreditNote($id);
        if ($cn['invoice_id']) {
            Billing::refreshInvoiceStatus((int) $cn['invoice_id']);
        }
        $this->db->transComplete();
        return redirect()->back()->with('success', 'Credit note voided.');
    }
}
