<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Billing;
use App\Libraries\Ledger;
use App\Libraries\Numbering;

/**
 * Customer deposits (e.g. 50% upfront for parts). Held as a liability (2300) until applied to the invoice.
 */
class Deposits extends BaseController
{
    public function index()
    {
        $rows = $this->db->table('customer_deposits d')->select('d.*, c.first_name, c.last_name, c.company_name, c.type, j.number job_number, i.number invoice_number')
            ->join('customers c', 'c.id = d.customer_id')->join('job_cards j', 'j.id = d.job_card_id', 'left')->join('invoices i', 'i.id = d.applied_invoice_id', 'left')
            ->orderBy('d.id', 'DESC')->limit(200)->get()->getResultArray();
        $held = array_sum(array_map(static fn ($r) => $r['status'] === 'held' ? (float) $r['amount'] : 0, $rows));
        return view('deposits/index', ['rows' => $rows, 'held' => $held]);
    }

    public function save()
    {
        $amount = round((float) $this->request->getPost('amount'), 2);
        $method = (string) $this->request->getPost('method');
        $jobId = (int) $this->request->getPost('job_card_id') ?: null;
        $custId = (int) $this->request->getPost('customer_id');
        if (!$custId && $jobId) {
            $custId = (int) $this->db->table('job_cards')->select('customer_id')->where('id', $jobId)->get()->getRow()->customer_id;
        }
        if ($amount <= 0 || !$custId || !isset(Ledger::METHOD_ACCOUNTS[$method])) {
            return redirect()->back()->with('error', 'Enter a valid amount and method.');
        }
        $date = $this->input('received_at') ? date('Y-m-d H:i:s', strtotime($this->input('received_at'))) : $this->now();
        try {
            $this->db->transStart();
            $this->db->table('customer_deposits')->insert([
                'number' => Numbering::next('payment'), 'customer_id' => $custId, 'job_card_id' => $jobId, 'quote_id' => (int) $this->request->getPost('quote_id') ?: null,
                'amount' => $amount, 'method' => $method, 'reference' => $this->input('reference'), 'received_at' => $date, 'status' => 'held',
                'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $id = (int) $this->db->insertID();
            Ledger::post('deposit', $id, substr($date, 0, 10), 'Customer deposit received', [
                ['account' => Ledger::METHOD_ACCOUNTS[$method], 'debit' => $amount],
                ['account' => '2300', 'credit' => $amount, 'customer_id' => $custId],
            ], $this->input('reference'));
            $this->db->transComplete();
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        if ($jobId) {
            Activity::log('job_card', $jobId, 'deposit', 'Deposit of ' . money($amount) . ' received (' . strtoupper($method) . ')');
        }
        return redirect()->back()->with('success', 'Deposit of ' . money($amount) . ' recorded. It will be applied when the job is invoiced.');
    }

    public function refund(int $id)
    {
        $d = $this->db->table('customer_deposits')->where(['id' => $id, 'status' => 'held'])->get()->getRowArray();
        if (!$d) {
            return redirect()->back()->with('error', 'Only unapplied deposits can be refunded.');
        }
        try {
            Ledger::post('deposit_refund', $id, date('Y-m-d'), 'Deposit refunded', [
                ['account' => '2300', 'debit' => $d['amount'], 'customer_id' => (int) $d['customer_id']],
                ['account' => Ledger::METHOD_ACCOUNTS[$d['method']] ?? '1000', 'credit' => $d['amount']],
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $this->db->table('customer_deposits')->where('id', $id)->update(['status' => 'refunded', 'updated_at' => $this->now()]);
        return redirect()->back()->with('success', 'Deposit refunded.');
    }

    /** Apply all held deposits for a job card to its invoice (called automatically on invoicing). */
    public static function applyToInvoice(int $jobId, int $invoiceId): float
    {
        $db = db_connect();
        $applied = 0.0;
        foreach ($db->table('customer_deposits')->where(['job_card_id' => $jobId, 'status' => 'held'])->get()->getResultArray() as $d) {
            $inv = $db->table('invoices')->select('balance_due')->where('id', $invoiceId)->get()->getRowArray();
            $amt = min((float) $d['amount'], (float) $inv['balance_due']);
            if ($amt <= 0) {
                break;
            }
            $pid = Billing::recordPayment($invoiceId, $amt, 'deposit', ['reference' => $d['number'], 'notes' => 'Deposit applied', 'notify' => false]);
            $db->table('customer_deposits')->where('id', $d['id'])->update(['status' => 'applied', 'applied_invoice_id' => $invoiceId, 'applied_payment_id' => $pid, 'applied_at' => date('Y-m-d H:i:s')]);
            $applied += $amt;
        }
        return $applied;
    }
}
