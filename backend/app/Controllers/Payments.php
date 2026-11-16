<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\Ledger;
use App\Libraries\Pdf;

class Payments extends BaseController
{
    private function query()
    {
        [$from, $to] = $this->range('month');
        $b = $this->db->table('payments p')
            ->select('p.*, i.number invoice_number, c.first_name, c.last_name, c.company_name, c.type, u.first_name rec_first')
            ->join('invoices i', 'i.id = p.invoice_id', 'left')->join('customers c', 'c.id = p.customer_id')->join('users u', 'u.id = p.recorded_by', 'left')
            ->where('p.paid_at >=', $from . ' 00:00:00')->where('p.paid_at <=', $to . ' 23:59:59');
        if ($m = $this->request->getGet('method')) {
            $b->where('p.method', $m);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('p.number', $q)->orLike('p.reference', $q)->orLike('i.number', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->groupEnd();
        }
        return [$b->orderBy('p.paid_at', 'DESC'), $from, $to];
    }

    public function index()
    {
        [$b, $from, $to] = $this->query();
        $all = (clone $b)->get()->getResultArray();
        [$rows, $pager] = $this->paginate($b, 30);
        $byMethod = [];
        foreach ($all as $p) {
            if ($p['status'] === 'completed') {
                $byMethod[$p['method']] = ($byMethod[$p['method']] ?? 0) + (float) $p['amount'];
            }
        }
        arsort($byMethod);
        $slips = Files::counts('payment', array_column($rows, 'id'));
        return view('payments/index', ['rows' => $rows, 'pager' => $pager, 'from' => $from, 'to' => $to, 'total' => array_sum($byMethod), 'byMethod' => $byMethod, 'slips' => $slips]);
    }

    public function export(string $format)
    {
        [$b, $from, $to] = $this->query();
        $rows = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'method_label' => strtoupper($r['method'])], $b->get()->getResultArray());
        return $this->exportTable($format, 'Payments Received', [
            'number' => ['label' => 'Receipt'], 'paid_at' => ['label' => 'Date'], 'customer' => ['label' => 'Customer'], 'invoice_number' => ['label' => 'Invoice'],
            'method_label' => ['label' => 'Method'], 'reference' => ['label' => 'Reference'], 'status' => ['label' => 'Status'], 'amount' => ['label' => 'Amount', 'type' => 'money'],
        ], $rows, ['Period' => fdate($from) . ' – ' . fdate($to)], ['number' => 'Total', 'amount' => array_sum(array_column($rows, 'amount'))]);
    }

    public function receipt(int $id)
    {
        $p = $this->db->table('payments')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Payment');
        $c = $this->db->table('customers')->where('id', $p['customer_id'])->get()->getRowArray();
        $i = $p['invoice_id'] ? $this->db->table('invoices')->where('id', $p['invoice_id'])->get()->getRowArray() : null;
        return Pdf::respond(Pdf::render('pdf/receipt', ['payment' => $p, 'customer' => $c, 'invoice' => $i]), $p['number'] . '.pdf');
    }

    /** Reverse a payment recorded in error. */
    public function delete(int $id)
    {
        if (!in_array(session('role'), ['admin', 'manager', 'accountant'], true)) {
            return redirect()->back()->with('error', 'Only managers or accountants can reverse payments.');
        }
        $p = $this->db->table('payments')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Payment');
        $this->db->transStart();
        $this->db->table('payments')->where('id', $id)->update(['status' => 'failed', 'notes' => trim(($p['notes'] ?? '') . "\nReversed by user #" . session('user_id') . ' on ' . $this->now()), 'updated_at' => $this->now()]);
        Ledger::postPayment($id); // removes journal for non-completed payments
        if ($p['invoice_id']) {
            Billing::refreshInvoiceStatus((int) $p['invoice_id']);
            Activity::log('invoice', (int) $p['invoice_id'], 'payment_reversed', 'Payment ' . $p['number'] . ' of ' . money($p['amount']) . ' reversed');
        }
        $this->db->transComplete();
        return redirect()->back()->with('success', 'Payment reversed.');
    }
}
