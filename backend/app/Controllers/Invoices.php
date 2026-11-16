<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\Ledger;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Pdf;

class Invoices extends BaseController
{
    public const METHODS = ['eft' => 'EFT / bank transfer', 'card' => 'Card (speedpoint)', 'cash' => 'Cash', 'payfast' => 'PayFast', 'ozow' => 'Ozow', 'other' => 'Other'];

    private function query()
    {
        $b = $this->db->table('invoices i')
            ->select('i.*, c.first_name, c.last_name, c.company_name, c.type, c.email, v.registration, v.make, v.model')
            ->join('customers c', 'c.id = i.customer_id')->join('vehicles v', 'v.id = i.vehicle_id', 'left')
            ->where('i.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('i.number', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->orLike('v.registration', $q)->orLike('i.order_number', $q)->groupEnd();
        }
        $s = $this->request->getGet('status');
        if ($s === 'overdue') {
            $b->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date <', date('Y-m-d'));
        } elseif ($s === 'outstanding') {
            $b->whereIn('i.status', ['unpaid', 'partial']);
        } elseif ($s) {
            $b->where('i.status', $s);
        }
        if (($from = $this->request->getGet('from')) && ($to = $this->request->getGet('to'))) {
            $b->where('i.issue_date >=', $from)->where('i.issue_date <=', $to);
        }
        return $b->orderBy('i.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $t = $this->db->table('invoices')->where('deleted_at', null);
        $stats = [
            'outstanding' => (float) (clone $t)->selectSum('balance_due')->whereIn('status', ['unpaid', 'partial'])->get()->getRow()->balance_due,
            'overdue'     => (float) (clone $t)->selectSum('balance_due')->whereIn('status', ['unpaid', 'partial'])->where('due_date <', date('Y-m-d'))->get()->getRow()->balance_due,
            'month'       => (float) (clone $t)->selectSum('total')->whereNotIn('status', ['draft', 'void'])->where('issue_date >=', date('Y-m-01'))->get()->getRow()->total,
            'collected'   => (float) $this->db->table('payments')->selectSum('amount')->where('status', 'completed')->where('paid_at >=', date('Y-m-01 00:00:00'))->get()->getRow()->amount,
        ];
        return view('invoices/index', compact('rows', 'pager', 'stats'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'net' => $r['subtotal'] - $r['discount_total'], 'status_label' => ucfirst(invoice_display_status($r))], $this->query()->get()->getResultArray());
        $sum = static fn ($k) => array_sum(array_column($rows, $k));
        return $this->exportTable($format, 'Invoices', [
            'number' => ['label' => 'Invoice'], 'issue_date' => ['label' => 'Date'], 'due_date' => ['label' => 'Due'], 'customer' => ['label' => 'Customer'],
            'registration' => ['label' => 'Reg.'], 'status_label' => ['label' => 'Status'], 'net' => ['label' => 'Excl. VAT', 'type' => 'money'], 'vat_total' => ['label' => 'VAT', 'type' => 'money'],
            'total' => ['label' => 'Total', 'type' => 'money'], 'amount_paid' => ['label' => 'Paid', 'type' => 'money'], 'balance_due' => ['label' => 'Balance', 'type' => 'money'],
        ], $rows, [], ['number' => 'Totals', 'net' => $sum('net'), 'vat_total' => $sum('vat_total'), 'total' => $sum('total'), 'amount_paid' => $sum('amount_paid'), 'balance_due' => $sum('balance_due')]);
    }

    public function form(?int $id = null)
    {
        if ($id) {
            $d = DocData::invoice($id);
            if (!$d) {
                return $this->notFound('Invoice');
            }
            if ((float) $d['invoice']['amount_paid'] > 0 || (float) $d['invoice']['amount_credited'] > 0 || $d['invoice']['status'] === 'void') {
                return redirect()->to(site_url('invoices/' . $id))->with('error', 'Invoices with payments (or voided) cannot be edited. Void and re-issue instead.');
            }
            return view('invoices/form', ['inv' => $d['invoice'], 'lines' => $d['items'], 'customer' => $d['customer'], 'vehicle' => $d['vehicle']]);
        }
        $cid = (int) $this->request->getGet('customer_id');
        $customer = $cid ? $this->db->table('customers')->where('id', $cid)->get()->getRowArray() : null;
        $terms = (int) (($customer['payment_terms_days'] ?? 0) ?: setting('default_payment_terms', 7));
        $vid = (int) $this->request->getGet('vehicle_id');
        return view('invoices/form', [
            'inv' => ['issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime("+{$terms} days")), 'terms' => setting('invoice_terms')],
            'lines' => [], 'customer' => $customer, 'vehicle' => $vid ? $this->db->table('vehicles')->where('id', $vid)->get()->getRowArray() : null,
        ]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['customer_id' => 'required|is_natural_no_zero', 'issue_date' => 'required|valid_date[Y-m-d]', 'due_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['customer_id', 'vehicle_id', 'issue_date', 'due_date', 'order_number', 'notes', 'terms']);
        $data['updated_at'] = $this->now();
        $asDraft = (bool) $this->request->getPost('draft');

        $this->db->transStart();
        if ($id) {
            $inv = $this->db->table('invoices')->where('id', $id)->get()->getRowArray();
            if ((float) $inv['amount_paid'] > 0) {
                return redirect()->to(site_url('invoices/' . $id))->with('error', 'Invoice has payments and cannot be edited.');
            }
            if ($inv['status'] === 'draft' && !$asDraft) {
                $data['status'] = 'unpaid';
            }
            $this->db->table('invoices')->where('id', $id)->update($data);
            Activity::log('invoice', $id, 'updated', 'Invoice updated');
        } else {
            $data += ['number' => Numbering::next('invoice'), 'branch_id' => session('branch_id'), 'status' => $asDraft ? 'draft' : 'unpaid',
                'public_token' => Billing::token(), 'created_by' => session('user_id'), 'created_at' => $this->now()];
            $this->db->table('invoices')->insert($data);
            $id = (int) $this->db->insertID();
            Activity::log('invoice', $id, 'created', 'Invoice ' . $data['number'] . ' created');
        }
        LineItems::sync('invoice', $id, (array) $this->request->getPost('items'));
        $status = $this->db->table('invoices')->select('status')->where('id', $id)->get()->getRow()->status;
        if ($status !== 'draft') {
            $this->returnStock($id); // re-issue so edited quantities stay accurate
            Billing::issueStock($id);
            Billing::refreshInvoiceStatus($id);
        }
        Ledger::postInvoice($id);
        $this->db->transComplete();

        if ($this->request->getPost('send') && $status !== 'draft') {
            $ok = Billing::sendInvoice($id);
            return redirect()->to(site_url('invoices/' . $id))->with($ok ? 'success' : 'warning', $ok ? 'Invoice saved and emailed.' : 'Saved, but the email failed. Check SMTP settings and the customer email.');
        }
        return redirect()->to(site_url('invoices/' . $id))->with('success', $status === 'draft' ? 'Draft saved.' : 'Tax invoice issued.');
    }

    public function show(int $id)
    {
        $d = DocData::invoice($id);
        if (!$d) {
            return $this->notFound('Invoice');
        }
        $payIds = array_column($d['payments'], 'id');
        $slips = [];
        foreach ($payIds as $pid) {
            foreach (Files::for('payment', (int) $pid) as $f) {
                $slips[] = $f;
            }
        }
        return view('invoices/show', $d + [
            'files' => Files::for('invoice', $id), 'slips' => $slips, 'activity' => Activity::for('invoice', $id),
            'emails' => $this->db->table('email_logs')->where(['entity_type' => 'invoice', 'entity_id' => $id])->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
            'methods' => self::METHODS,
            'credits' => $this->db->table('credit_notes')->where(['invoice_id' => $id, 'deleted_at' => null])->orderBy('id')->get()->getResultArray(),
        ]);
    }

    public function pdf(int $id)
    {
        $d = DocData::invoice($id) ?? $this->notFound('Invoice');
        return Pdf::respond(Pdf::render('pdf/invoice', $d), $d['invoice']['number'] . '.pdf', $this->request->getGet('download') !== '1');
    }

    public function email(int $id)
    {
        $ok = Billing::sendInvoice($id, $this->input('to'));
        return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Invoice emailed with PDF and pay-online link.' : 'Email failed. Check the address and SMTP settings.');
    }

    public function remind(int $id)
    {
        $ok = Billing::sendInvoice($id, null, 'reminder');
        return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Payment reminder sent.' : 'Reminder failed. Check the customer email and SMTP settings.');
    }

    /** Record a manual payment (EFT, cash, card…) with optional proof-of-payment upload. */
    public function payment(int $id)
    {
        $inv = $this->db->table('invoices')->where('id', $id)->get()->getRowArray();
        $amount = round((float) $this->request->getPost('amount'), 2);
        $method = (string) $this->request->getPost('method');
        if (!$inv || in_array($inv['status'], ['void', 'draft'], true)) {
            return redirect()->back()->with('error', 'Payments can only be recorded on issued invoices.');
        }
        if ($amount <= 0 || !array_key_exists($method, self::METHODS)) {
            return redirect()->back()->with('error', 'Enter a valid amount and payment method.');
        }
        if ($amount - (float) $inv['balance_due'] > 0.009) {
            return redirect()->back()->with('error', 'Amount is more than the balance due (' . money($inv['balance_due']) . ').');
        }
        $paidAt = $this->input('paid_at') ? date('Y-m-d H:i:s', strtotime($this->input('paid_at'))) : $this->now();
        $pid = Billing::recordPayment($id, $amount, $method, [
            'reference' => $this->input('reference'), 'paid_at' => $paidAt, 'notes' => $this->input('notes'),
            'notify' => (bool) $this->request->getPost('send_receipt'),
        ]);
        $slip = $this->request->getFile('slip');
        if ($slip && $slip->isValid()) {
            try {
                Files::store($slip, 'payment', $pid, 'slip', 'Proof of payment for ' . $inv['number']);
            } catch (\Throwable $e) {
                return redirect()->back()->with('warning', 'Payment recorded, but the slip upload failed: ' . $e->getMessage());
            }
        }
        return redirect()->back()->with('success', 'Payment of ' . money($amount) . ' recorded.');
    }

    private function returnStock(int $id): void
    {
        foreach ($this->db->table('stock_movements')->where(['reference_type' => 'invoice', 'reference_id' => $id])->get()->getResultArray() as $m) {
            $this->db->table('products')->set('qty_on_hand', 'qty_on_hand + ' . abs((float) $m['quantity']), false)->where('id', $m['product_id'])->update();
        }
        $this->db->table('stock_movements')->where(['reference_type' => 'invoice', 'reference_id' => $id])->delete();
    }

    public function void(int $id)
    {
        $inv = $this->db->table('invoices')->where('id', $id)->get()->getRowArray();
        if ((float) $inv['amount_paid'] > 0 || (float) $inv['amount_credited'] > 0) {
            return redirect()->back()->with('error', 'Reverse the payments and void any credit notes before voiding this invoice.');
        }
        $this->db->transStart();
        $this->db->table('invoices')->where('id', $id)->update(['status' => 'void', 'voided_at' => $this->now(), 'balance_due' => 0, 'updated_at' => $this->now()]);
        $this->returnStock($id);
        Ledger::postInvoice($id); // removes the journal for void invoices
        if ($inv['job_card_id']) {
            $this->db->table('job_cards')->where('id', $inv['job_card_id'])->update(['status' => 'completed']);
        }
        $this->db->transComplete();
        Activity::log('invoice', $id, 'void', 'Invoice voided: ' . ($this->input('reason') ?? 'no reason given'));
        return redirect()->back()->with('success', 'Invoice voided. Stock returned and ledger reversed.');
    }
}
