<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\LineItems;
use App\Libraries\Mailer;
use App\Libraries\Numbering;
use App\Libraries\Pdf;
use App\Libraries\Purchasing;

class PurchaseOrders extends BaseController
{
    private function query()
    {
        $b = $this->db->table('purchase_orders po')->select('po.*, s.name supplier_name, j.number job_number')
            ->join('suppliers s', 's.id = po.supplier_id')->join('job_cards j', 'j.id = po.job_card_id', 'left')->where('po.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('po.number', $q)->orLike('s.name', $q)->orLike('j.number', $q)->groupEnd();
        }
        if ($s = $this->request->getGet('status')) {
            $b->where('po.status', $s);
        }
        return $b->orderBy('po.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $open = (float) $this->db->table('purchase_orders')->selectSum('total')->where('deleted_at', null)->whereIn('status', ['draft', 'sent'])->get()->getRow()->total;
        return view('purchases/po_index', compact('rows', 'pager', 'open'));
    }

    public function export(string $format)
    {
        $rows = $this->query()->get()->getResultArray();
        return $this->exportTable($format, 'Purchase Orders', [
            'number' => ['label' => 'PO'], 'order_date' => ['label' => 'Date'], 'supplier_name' => ['label' => 'Supplier'], 'job_number' => ['label' => 'Job card'],
            'status' => ['label' => 'Status'], 'total' => ['label' => 'Total', 'type' => 'money'],
        ], $rows, [], ['number' => 'Total', 'total' => array_sum(array_column($rows, 'total'))]);
    }

    private function data(?int $id): array
    {
        $po = $id ? $this->db->table('purchase_orders')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : null;
        return [
            'po' => $po ?? ['order_date' => date('Y-m-d'), 'expected_date' => date('Y-m-d', strtotime('+2 days')), 'supplier_id' => $this->request->getGet('supplier_id'), 'job_card_id' => $this->request->getGet('job_card_id')],
            'lines' => $id ? LineItems::get('purchase_order', $id) : [],
            'suppliers' => $this->db->table('suppliers')->select('id, name, email')->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
            'jobs' => $this->db->table('job_cards j')->select('j.id, j.number, v.registration')->join('vehicles v', 'v.id = j.vehicle_id')
                ->where('j.deleted_at', null)->whereNotIn('j.status', ['collected', 'cancelled', 'invoiced'])->orderBy('j.id', 'DESC')->limit(100)->get()->getResultArray(),
        ];
    }

    public function form(?int $id = null)
    {
        $d = $this->data($id);
        if ($id && empty($d['po']['id'])) {
            return $this->notFound('Purchase order');
        }
        if ($id && in_array($d['po']['status'], ['billed', 'cancelled'], true)) {
            return redirect()->to(site_url('purchase-orders/' . $id))->with('error', 'This purchase order can no longer be edited.');
        }
        return view('purchases/po_form', $d);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['supplier_id' => 'required|is_natural_no_zero', 'order_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['supplier_id', 'job_card_id', 'order_date', 'expected_date', 'notes']) + ['updated_at' => $this->now()];
        if ($id) {
            $this->db->table('purchase_orders')->where('id', $id)->update($data);
        } else {
            $this->db->table('purchase_orders')->insert($data + ['number' => Numbering::next('purchase_order'), 'branch_id' => session('branch_id'), 'status' => 'draft', 'created_by' => session('user_id'), 'created_at' => $this->now()]);
            $id = (int) $this->db->insertID();
        }
        LineItems::sync('purchase_order', $id, (array) $this->request->getPost('items'));
        Activity::log('purchase_order', $id, 'saved', 'Purchase order saved');
        if ($this->request->getPost('send')) {
            return $this->email($id);
        }
        return redirect()->to(site_url('purchase-orders/' . $id))->with('success', 'Purchase order saved.');
    }

    private function docData(int $id): array
    {
        $po = $this->db->table('purchase_orders')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Purchase order');
        return [
            'po' => $po, 'items' => LineItems::get('purchase_order', $id),
            'supplier' => $this->db->table('suppliers')->where('id', $po['supplier_id'])->get()->getRowArray(),
            'job' => $po['job_card_id'] ? $this->db->table('job_cards j')->select('j.id, j.number, v.registration, v.make, v.model')->join('vehicles v', 'v.id = j.vehicle_id')->where('j.id', $po['job_card_id'])->get()->getRowArray() : null,
        ];
    }

    public function show(int $id)
    {
        $d = $this->docData($id);
        $d['bill'] = $this->db->table('supplier_bills')->where(['purchase_order_id' => $id, 'deleted_at' => null])->where('status !=', 'void')->get()->getRowArray();
        $d['activity'] = Activity::for('purchase_order', $id);
        return view('purchases/po_show', $d);
    }

    public function pdf(int $id)
    {
        $d = $this->docData($id);
        return Pdf::respond(Pdf::render('pdf/purchase_order', $d), $d['po']['number'] . '.pdf');
    }

    public function email(int $id)
    {
        $d = $this->docData($id);
        $to = $this->input('to') ?: $d['supplier']['email'];
        if (!$to) {
            return redirect()->to(site_url('purchase-orders/' . $id))->with('error', 'The supplier has no email address. Add one or download the PDF.');
        }
        $ok = Mailer::send($to, 'Purchase order ' . $d['po']['number'] . ' from ' . setting('company_name'),
            '<p>Good day ' . esc($d['supplier']['contact_person'] ?: $d['supplier']['name']) . ',</p><p>Please find attached purchase order <b>' . esc($d['po']['number']) . '</b>'
            . ($d['po']['expected_date'] ? ', required by <b>' . fdate($d['po']['expected_date']) . '</b>' : '') . '. Kindly quote the PO number on your invoice.</p><p>Thank you.</p>',
            [['content' => Pdf::render('pdf/purchase_order', $d), 'name' => $d['po']['number'] . '.pdf']], 'purchase_order', $id);
        if ($ok && $d['po']['status'] === 'draft') {
            $this->db->table('purchase_orders')->where('id', $id)->update(['status' => 'sent']);
        }
        return redirect()->to(site_url('purchase-orders/' . $id))->with($ok ? 'success' : 'error', $ok ? 'Purchase order emailed to ' . $to : 'Email failed. Check SMTP settings.');
    }

    /** Goods received: create the supplier bill and bring stock in. */
    public function receive(int $id)
    {
        $po = $this->db->table('purchase_orders')->where('id', $id)->get()->getRowArray();
        if (!$po || in_array($po['status'], ['billed', 'cancelled'], true)) {
            return redirect()->back()->with('error', 'This purchase order has already been received or was cancelled.');
        }
        try {
            $billId = Purchasing::billFromPo($id, $this->input('supplier_invoice'), $this->input('bill_date'));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->to(site_url('bills/' . $billId))->with('success', 'Goods received. Stock updated and supplier bill created.');
    }

    public function cancel(int $id)
    {
        $this->db->table('purchase_orders')->where('id', $id)->whereIn('status', ['draft', 'sent'])->update(['status' => 'cancelled', 'updated_at' => $this->now()]);
        Activity::log('purchase_order', $id, 'cancelled', 'Purchase order cancelled');
        return redirect()->back()->with('success', 'Purchase order cancelled.');
    }
}
