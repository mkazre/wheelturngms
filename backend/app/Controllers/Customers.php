<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Mailer;
use App\Libraries\Numbering;
use App\Libraries\Pdf;

class Customers extends BaseController
{
    private function query()
    {
        $q = trim((string) $this->request->getGet('q'));
        $b = $this->db->table('customers c')
            ->select('c.*, (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id AND v.deleted_at IS NULL) vehicles_count')
            ->select("(SELECT COALESCE(SUM(i.balance_due),0) FROM invoices i WHERE i.customer_id = c.id AND i.deleted_at IS NULL AND i.status IN ('unpaid','partial')) balance")
            ->select("(SELECT COALESCE(SUM(i.total),0) FROM invoices i WHERE i.customer_id = c.id AND i.deleted_at IS NULL AND i.status NOT IN ('draft','void')) lifetime")
            ->where('c.deleted_at', null);
        if ($q !== '') {
            $b->groupStart()->like('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)
                ->orLike('c.email', $q)->orLike('c.phone', $q)->orLike('c.code', $q)->orLike('c.vat_number', $q)
                ->orWhere("c.id IN (SELECT customer_id FROM vehicles WHERE registration LIKE " . $this->db->escape('%' . $q . '%') . ')', null, false)
                ->groupEnd();
        }
        if ($t = $this->request->getGet('type')) {
            $b->where('c.type', $t);
        }
        $sort = $this->request->getGet('sort');
        match ($sort) {
            'balance'  => $b->orderBy('balance', 'DESC'),
            'lifetime' => $b->orderBy('lifetime', 'DESC'),
            default    => $b->orderBy('c.id', 'DESC'),
        };
        return $b;
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $stats = [
            'total'    => $this->db->table('customers')->where('deleted_at', null)->countAllResults(),
            'business' => $this->db->table('customers')->where(['deleted_at' => null, 'type' => 'business'])->countAllResults(),
            'month'    => $this->db->table('customers')->where('deleted_at', null)->where('created_at >=', date('Y-m-01'))->countAllResults(),
        ];
        return view('customers/index', compact('rows', 'pager', 'stats'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['name' => customer_name($r)], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Customers', [
            'code' => ['label' => 'Code'], 'name' => ['label' => 'Customer'], 'type' => ['label' => 'Type'], 'phone' => ['label' => 'Phone'],
            'email' => ['label' => 'Email'], 'vat_number' => ['label' => 'VAT no.'], 'vehicles_count' => ['label' => 'Vehicles', 'type' => 'number'],
            'lifetime' => ['label' => 'Lifetime sales', 'type' => 'money'], 'balance' => ['label' => 'Balance', 'type' => 'money'],
        ], $rows, [], ['name' => 'Total', 'lifetime' => array_sum(array_column($rows, 'lifetime')), 'balance' => array_sum(array_column($rows, 'balance'))]);
    }

    /** JSON for searchable selects. */
    public function lookup()
    {
        $q = trim((string) $this->request->getGet('q'));
        $b = $this->db->table('customers')->select('id, type, first_name, last_name, company_name, phone, email')->where('deleted_at', null);
        if ($q !== '') {
            $b->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('company_name', $q)->orLike('phone', $q)->orLike('email', $q)->groupEnd();
        }
        $out = array_map(static fn ($c) => ['id' => $c['id'], 'text' => customer_name($c) . ($c['phone'] ? ' · ' . $c['phone'] : ''), 'email' => $c['email']], $b->orderBy('first_name')->limit(30)->get()->getResultArray());
        return $this->json($out);
    }

    public function form(?int $id = null)
    {
        $customer = $id ? $this->db->table('customers')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : null;
        if ($id && !$customer) {
            return $this->notFound('Customer');
        }
        return view('customers/form', ['c' => $customer ?? ['type' => 'individual', 'payment_terms_days' => 0]]);
    }

    public function save(?int $id = null)
    {
        $rules = [
            'type'  => 'required|in_list[individual,business]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|max_length[50]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['type', 'first_name', 'last_name', 'company_name', 'vat_number', 'registration_number', 'id_number', 'email', 'phone', 'alt_phone',
            'address_line1', 'address_line2', 'city', 'province', 'postal_code', 'source', 'tags', 'notes']);
        if (!$data['first_name'] && !$data['company_name']) {
            return redirect()->back()->withInput()->with('error', 'Enter a name or company name.');
        }
        $data['email'] = $data['email'] ? strtolower($data['email']) : null;
        $data['payment_terms_days'] = (int) $this->input('payment_terms_days', 0);
        $data['credit_limit']       = (float) $this->input('credit_limit', 0);
        $data['marketing_consent']  = $this->request->getPost('marketing_consent') ? 1 : 0;
        $data['updated_at']         = $this->now();

        if ($id) {
            $this->db->table('customers')->where('id', $id)->update($data);
            Activity::log('customer', $id, 'updated', 'Updated customer ' . customer_name($data));
        } else {
            $data += ['code' => Numbering::next('customer'), 'branch_id' => session('branch_id'), 'is_active' => 1, 'created_at' => $this->now(),
                'popia_consent_at' => $this->request->getPost('popia_consent') ? $this->now() : null];
            $this->db->table('customers')->insert($data);
            $id = (int) $this->db->insertID();
            Activity::log('customer', $id, 'created', 'Added customer ' . customer_name($data));
        }
        if ($ret = $this->request->getPost('return_to')) {
            return redirect()->to(site_url($ret) . (str_contains($ret, '?') ? '&' : '?') . 'customer_id=' . $id)->with('success', 'Customer saved.');
        }
        return redirect()->to(site_url('customers/' . $id))->with('success', 'Customer saved.');
    }

    public function show(int $id)
    {
        $c = $this->db->table('customers')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (!$c) {
            return $this->notFound('Customer');
        }
        $vehicles = $this->db->table('vehicles')->where(['customer_id' => $id, 'deleted_at' => null])->get()->getResultArray();
        $jobs     = $this->db->table('job_cards j')->select('j.*, v.registration, v.make, v.model')->join('vehicles v', 'v.id = j.vehicle_id', 'left')
            ->where(['j.customer_id' => $id, 'j.deleted_at' => null])->orderBy('j.id', 'DESC')->limit(50)->get()->getResultArray();
        $invoices = $this->db->table('invoices')->where(['customer_id' => $id, 'deleted_at' => null])->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();
        $quotes   = $this->db->table('quotes')->where(['customer_id' => $id, 'deleted_at' => null])->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();
        $payments = $this->db->table('payments')->where(['customer_id' => $id])->orderBy('paid_at', 'DESC')->limit(50)->get()->getResultArray();
        $portal   = $this->db->table('users')->where(['customer_id' => $id, 'deleted_at' => null])->get()->getRowArray();

        $stats = [
            'lifetime' => array_sum(array_map(static fn ($i) => in_array($i['status'], ['draft', 'void'], true) ? 0 : (float) $i['total'], $invoices)),
            'balance'  => array_sum(array_map(static fn ($i) => in_array($i['status'], ['unpaid', 'partial'], true) ? (float) $i['balance_due'] : 0, $invoices)),
            'visits'   => count($jobs),
            'last'     => $jobs[0]['created_at'] ?? null,
        ];
        return view('customers/show', [
            'c' => $c, 'vehicles' => $vehicles, 'jobs' => $jobs, 'invoices' => $invoices, 'quotes' => $quotes, 'payments' => $payments,
            'stats' => $stats, 'portal' => $portal, 'files' => Files::for('customer', $id), 'activity' => Activity::for('customer', $id),
        ]);
    }

    public function delete(int $id)
    {
        $open = $this->db->table('invoices')->where(['customer_id' => $id, 'deleted_at' => null])->whereIn('status', ['unpaid', 'partial'])->countAllResults();
        if ($open) {
            return redirect()->back()->with('error', 'This customer has unpaid invoices and cannot be archived.');
        }
        $this->db->table('customers')->where('id', $id)->update(['deleted_at' => $this->now()]);
        Activity::log('customer', $id, 'deleted', 'Archived customer');
        return redirect()->to(site_url('customers'))->with('success', 'Customer archived.');
    }

    /** Customer statement PDF (opening balance, invoices, payments, running balance, ageing). */
    public function statement(int $id)
    {
        [$from, $to] = $this->range('year');
        $c = $this->db->table('customers')->where('id', $id)->get()->getRowArray();
        if (!$c) {
            return $this->notFound('Customer');
        }
        $pdf = \App\Libraries\Engagement::statementPdf($c, $from, $to);
        if ($this->request->getGet('email') && $c['email']) {
            $ok = \App\Libraries\Mailer::send($c['email'], 'Statement of account: ' . setting('company_name'), '<p>Hi ' . esc(customer_name($c)) . ',</p><p>Please find your statement attached.</p>', [['content' => $pdf, 'name' => 'Statement-' . ($c['code'] ?: $id) . '.pdf']], 'customer', $id);
            return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Statement emailed.' : 'Statement email failed.');
        }
        return Pdf::respond($pdf, 'Statement-' . ($c['code'] ?: $id) . '.pdf');
    }

    /** Create / reset a customer portal + mobile app login. */
    public function portalAccess(int $id)
    {
        $c = $this->db->table('customers')->where('id', $id)->get()->getRowArray();
        if (!$c || !$c['email']) {
            return redirect()->back()->with('error', 'The customer needs an email address first.');
        }
        $existing = $this->db->table('users')->where('email', strtolower($c['email']))->get()->getRowArray();
        if ($existing && (int) $existing['customer_id'] !== $id) {
            return redirect()->back()->with('error', 'That email is already used by a staff account.');
        }
        $temp = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
        $data = ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'is_active' => 1, 'updated_at' => $this->now()];
        if ($existing) {
            $this->db->table('users')->where('id', $existing['id'])->update($data);
        } else {
            $this->db->table('users')->insert($data + [
                'role' => 'customer', 'customer_id' => $id, 'first_name' => $c['first_name'] ?: $c['company_name'], 'last_name' => $c['last_name'],
                'email' => strtolower($c['email']), 'phone' => $c['phone'], 'created_at' => $this->now(),
            ]);
        }
        $ok = Mailer::send($c['email'], 'Your ' . setting('company_name') . ' customer account',
            '<p>Hi ' . esc(customer_name($c)) . ',</p><p>Your customer account is ready. Track your vehicle\'s service progress, approve quotes and pay invoices online.</p>'
            . '<p><b>Email:</b> ' . esc($c['email']) . '<br><b>Temporary password:</b> <code style="font-size:15px">' . esc($temp) . '</code></p>'
            . view('emails/_pay_button', ['url' => site_url('login'), 'label' => 'Sign in']) . '<p style="color:#64748b;font-size:12px">Please change your password after signing in.</p>', [], 'customer', $id);
        Activity::log('customer', $id, 'portal', 'Portal access ' . ($existing ? 'reset' : 'created'));
        return redirect()->back()->with($ok ? 'success' : 'warning', $ok ? 'Login details emailed to the customer.' : "Account created, but the email could not be sent. Temporary password: {$temp}");
    }
}
