<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Pdf;

class Quotes extends BaseController
{
    private function query()
    {
        // Auto-expire stale quotes
        $this->db->table('quotes')->whereIn('status', ['draft', 'sent'])->where('expiry_date <', date('Y-m-d'))->update(['status' => 'expired']);

        $b = $this->db->table('quotes q')
            ->select('q.*, c.first_name, c.last_name, c.company_name, c.type, v.registration, v.make, v.model')
            ->join('customers c', 'c.id = q.customer_id')->join('vehicles v', 'v.id = q.vehicle_id', 'left')
            ->where('q.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('q.number', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->orLike('v.registration', $q)->groupEnd();
        }
        if ($s = $this->request->getGet('status')) {
            $b->where('q.status', $s);
        }
        return $b->orderBy('q.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $stats = $this->db->table('quotes')->select("status, COUNT(*) n, COALESCE(SUM(total),0) amt")->where('deleted_at', null)->groupBy('status')->get()->getResultArray();
        return view('quotes/index', ['rows' => $rows, 'pager' => $pager, 'stats' => array_column($stats, null, 'status')]);
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['customer' => customer_name($r)], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Quotes', [
            'number' => ['label' => 'Quote'], 'issue_date' => ['label' => 'Date'], 'expiry_date' => ['label' => 'Expires'], 'customer' => ['label' => 'Customer'],
            'registration' => ['label' => 'Reg.'], 'status' => ['label' => 'Status'], 'subtotal' => ['label' => 'Subtotal', 'type' => 'money'],
            'vat_total' => ['label' => 'VAT', 'type' => 'money'], 'total' => ['label' => 'Total', 'type' => 'money'],
        ], $rows, [], ['number' => 'Total', 'total' => array_sum(array_column($rows, 'total'))]);
    }

    public function form(?int $id = null)
    {
        if ($id) {
            $d = DocData::quote($id);
            if (!$d) {
                return $this->notFound('Quote');
            }
            return view('quotes/form', ['q' => $d['quote'], 'lines' => $d['items'], 'customer' => $d['customer'], 'vehicle' => $d['vehicle']]);
        }
        $cid = (int) $this->request->getGet('customer_id');
        $vid = (int) $this->request->getGet('vehicle_id');
        return view('quotes/form', [
            'q' => ['issue_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+' . (int) setting('quote_validity_days', 14) . ' days')), 'terms' => setting('quote_terms')],
            'lines' => [], 'customer' => $cid ? $this->db->table('customers')->where('id', $cid)->get()->getRowArray() : null,
            'vehicle' => $vid ? $this->db->table('vehicles')->where('id', $vid)->get()->getRowArray() : null,
        ]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['customer_id' => 'required|is_natural_no_zero', 'issue_date' => 'required|valid_date[Y-m-d]', 'expiry_date' => 'required|valid_date[Y-m-d]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['customer_id', 'vehicle_id', 'issue_date', 'expiry_date', 'notes', 'terms']);
        $data['updated_at'] = $this->now();
        if ($id) {
            $this->db->table('quotes')->where('id', $id)->update($data);
            Activity::log('quote', $id, 'updated', 'Quote updated');
        } else {
            $data += ['number' => Numbering::next('quote'), 'branch_id' => session('branch_id'), 'status' => 'draft', 'public_token' => Billing::token(), 'created_by' => session('user_id'), 'created_at' => $this->now()];
            $this->db->table('quotes')->insert($data);
            $id = (int) $this->db->insertID();
            Activity::log('quote', $id, 'created', 'Quote ' . $data['number'] . ' created');
        }
        LineItems::sync('quote', $id, (array) $this->request->getPost('items'));
        if ($this->request->getPost('send')) {
            $ok = Billing::sendQuote($id);
            return redirect()->to(site_url('quotes/' . $id))->with($ok ? 'success' : 'warning', $ok ? 'Quote saved and emailed.' : 'Quote saved, but the email could not be sent. Check the customer email and SMTP settings.');
        }
        return redirect()->to(site_url('quotes/' . $id))->with('success', 'Quote saved.');
    }

    public function show(int $id)
    {
        $d = DocData::quote($id);
        if (!$d) {
            return $this->notFound('Quote');
        }
        return view('quotes/show', $d + ['files' => Files::for('quote', $id), 'activity' => Activity::for('quote', $id)]);
    }

    public function pdf(int $id)
    {
        $d = DocData::quote($id) ?? $this->notFound('Quote');
        return Pdf::respond(Pdf::render('pdf/quote', $d), $d['quote']['number'] . '.pdf', $this->request->getGet('download') !== '1');
    }

    public function email(int $id)
    {
        $ok = Billing::sendQuote($id, $this->input('to'));
        return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Quote emailed.' : 'Email failed. Check the address and SMTP settings.');
    }

    public function status(int $id)
    {
        $s = (string) $this->request->getPost('status');
        if (!in_array($s, ['draft', 'sent', 'accepted', 'declined'], true)) {
            return redirect()->back()->with('error', 'Invalid status.');
        }
        $update = ['status' => $s, 'updated_at' => $this->now()];
        if (in_array($s, ['sent', 'accepted', 'declined'], true)) {
            $update[$s . '_at'] = $this->now();
        }
        $this->db->table('quotes')->where('id', $id)->update($update);
        Activity::log('quote', $id, 'status', 'Marked as ' . $s);
        return redirect()->back()->with('success', 'Quote marked as ' . $s . '.');
    }

    /** Convert to a job card (default) or straight to an invoice. */
    public function convert(int $id)
    {
        if ($this->request->getPost('to') === 'invoice') {
            $q = $this->db->table('quotes')->where('id', $id)->get()->getRowArray();
            $c = $this->db->table('customers')->where('id', $q['customer_id'])->get()->getRowArray();
            $terms = (int) ($c['payment_terms_days'] ?: setting('default_payment_terms', 7));
            $this->db->table('invoices')->insert([
                'number' => Numbering::next('invoice'), 'branch_id' => $q['branch_id'], 'customer_id' => $q['customer_id'], 'vehicle_id' => $q['vehicle_id'],
                'quote_id' => $id, 'status' => 'unpaid', 'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime("+{$terms} days")),
                'notes' => $q['notes'], 'terms' => setting('invoice_terms'), 'public_token' => Billing::token(), 'created_by' => session('user_id'),
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $invId = (int) $this->db->insertID();
            LineItems::copy('quote', $id, 'invoice', $invId);
            Billing::issueStock($invId);
            \App\Libraries\Ledger::postInvoice($invId);
            $this->db->table('quotes')->where('id', $id)->update(['status' => 'converted']);
            Activity::log('invoice', $invId, 'created', 'Invoiced from quote ' . $q['number']);
            return redirect()->to(site_url('invoices/' . $invId))->with('success', 'Tax invoice created from quote.');
        }
        $jobId = Billing::quoteToJobCard($id);
        return redirect()->to(site_url('job-cards/' . $jobId))->with('success', 'Job card created from quote.');
    }

    public function delete(int $id)
    {
        $this->db->table('quotes')->where('id', $id)->update(['deleted_at' => $this->now()]);
        Activity::log('quote', $id, 'deleted', 'Quote deleted');
        return redirect()->to(site_url('quotes'))->with('success', 'Quote deleted.');
    }
}
