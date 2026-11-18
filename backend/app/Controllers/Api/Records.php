<?php

namespace App\Controllers\Api;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\Numbering;

/** Customers, vehicles, quotes, invoices, products and files. */
class Records extends BaseApi
{
    // ------------------------------------------------------------ customers

    public function customers()
    {
        if ($r = $this->deny('customers')) {
            return $r;
        }
        $b = $this->db->table('customers c')->select("c.id, c.type, c.first_name, c.last_name, c.company_name, c.phone, c.email, (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id AND v.deleted_at IS NULL) vehicles, (SELECT COALESCE(SUM(i.balance_due),0) FROM invoices i WHERE i.customer_id = c.id AND i.deleted_at IS NULL AND i.status IN ('unpaid','partial')) balance", false)
            ->where('c.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->orLike('c.phone', $q)->orLike('c.email', $q)
                ->orWhere('c.id IN (SELECT customer_id FROM vehicles WHERE registration LIKE ' . $this->db->escape('%' . $q . '%') . ')', null, false)->groupEnd();
        }
        $rows = $b->orderBy('c.id', 'DESC')->limit(40)->get()->getResultArray();
        return $this->ok(['items' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'name' => customer_name($c), 'phone' => $c['phone'], 'email' => $c['email'], 'type' => $c['type'], 'vehicles' => (int) $c['vehicles'], 'balance' => (float) $c['balance']], $rows)]);
    }

    public function customer(int $id)
    {
        if ($r = $this->deny('customers')) {
            return $r;
        }
        $c = $this->db->table('customers')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (!$c) {
            return $this->fail('Customer not found', 404);
        }
        $vehicles = $this->db->table('vehicles')->where(['customer_id' => $id, 'deleted_at' => null])->get()->getResultArray();
        $jobs = $this->db->table('job_cards j')->select('j.id, j.number, j.status, j.created_at, j.total, v.registration')->join('vehicles v', 'v.id = j.vehicle_id')->where(['j.customer_id' => $id, 'j.deleted_at' => null])->orderBy('j.id', 'DESC')->limit(20)->get()->getResultArray();
        $invoices = $this->db->table('invoices')->select('id, number, issue_date, due_date, total, balance_due, status')->where(['customer_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->orderBy('id', 'DESC')->limit(20)->get()->getResultArray();
        return $this->ok([
            'id' => (int) $c['id'], 'name' => customer_name($c), 'type' => $c['type'], 'phone' => $c['phone'], 'email' => $c['email'], 'vat_number' => $c['vat_number'],
            'address' => implode(', ', array_filter([$c['address_line1'], $c['address_line2'], $c['city']])), 'notes' => $c['notes'],
            'balance' => array_sum(array_map(static fn ($i) => in_array($i['status'], ['unpaid', 'partial'], true) ? (float) $i['balance_due'] : 0, $invoices)),
            'vehicles' => array_map(fn ($v) => ['id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'odometer' => $v['odometer'] ? (int) $v['odometer'] : null, 'next_service_date' => $v['next_service_date']], $vehicles),
            'jobs' => array_map(static fn ($j) => ['id' => (int) $j['id'], 'number' => $j['number'], 'status' => $j['status'], 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status'], 'status_color' => job_statuses()[$j['status']]['hex'] ?? '#64748b', 'date' => $j['created_at'], 'registration' => $j['registration'], 'total' => (float) $j['total']], $jobs),
            'invoices' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'number' => $i['number'], 'date' => $i['issue_date'], 'total' => (float) $i['total'], 'balance' => (float) $i['balance_due'], 'status' => invoice_display_status($i)], $invoices),
        ]);
    }

    public function createCustomer()
    {
        if ($r = $this->deny('customers')) {
            return $r;
        }
        $b = $this->body();
        if (!$this->val($b, 'first_name') && !$this->val($b, 'company_name')) {
            return $this->fail('Enter a name or company.');
        }
        $email = $this->val($b, 'email') ? strtolower($this->val($b, 'email')) : null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('That email address is not valid.');
        }
        $this->db->table('customers')->insert([
            'code' => Numbering::next('customer'), 'branch_id' => session('branch_id'), 'type' => $this->val($b, 'company_name') ? 'business' : 'individual',
            'first_name' => $this->val($b, 'first_name'), 'last_name' => $this->val($b, 'last_name'), 'company_name' => $this->val($b, 'company_name'),
            'email' => $email, 'phone' => $this->val($b, 'phone'), 'source' => 'Mobile app', 'popia_consent_at' => !empty($b['popia']) ? $this->now() : null,
            'marketing_consent' => !empty($b['marketing']) ? 1 : 0, 'is_active' => 1, 'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        $id = (int) $this->db->insertID();
        Activity::log('customer', $id, 'created', 'Customer added from the mobile app');
        return $this->ok(['id' => $id], 201);
    }

    // ------------------------------------------------------------ vehicles

    public function vehicles()
    {
        if ($r = $this->deny('vehicles')) {
            return $r;
        }
        $b = $this->db->table('vehicles v')->select('v.*, c.first_name, c.last_name, c.company_name, c.type')->join('customers c', 'c.id = v.customer_id')->where('v.deleted_at', null);
        if ($cid = (int) $this->request->getGet('customer_id')) {
            $b->where('v.customer_id', $cid);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $compact = $this->db->escapeLikeString(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $q)));
            $b->groupStart()->like('v.registration', $q)->orLike('v.vin', $q)->orLike('v.make', $q)->orLike('v.model', $q)
                ->orLike("CONCAT(c.first_name, ' ', c.last_name)", $q, 'both', null, false)->orLike('c.company_name', $q);
            if ($compact !== '') {
                $b->orWhere("UPPER(REPLACE(REPLACE(v.registration, ' ', ''), '-', '')) LIKE '%" . $compact . "%'", null, false);
            }
            $b->groupEnd();
        }
        return $this->ok(['items' => array_map(fn ($v) => ['id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'owner' => $this->isMechanic() ? null : customer_name($v), 'customer_id' => (int) $v['customer_id'], 'odometer' => $v['odometer'] ? (int) $v['odometer'] : null], $b->orderBy('v.id', 'DESC')->limit(40)->get()->getResultArray())]);
    }

    public function vehicle(int $id)
    {
        if ($r = $this->deny('vehicles')) {
            return $r;
        }
        $v = $this->db->table('vehicles')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (!$v) {
            return $this->fail('Vehicle not found', 404);
        }
        $jobs = $this->db->table('job_cards')->select('id, number, status, created_at, odometer_in, customer_complaint, total')->where(['vehicle_id' => $id, 'deleted_at' => null])->orderBy('id', 'DESC')->limit(30)->get()->getResultArray();
        $work = [];
        foreach ($this->db->table('line_items')->select('document_id, description')->where('document_type', 'job_card')->whereIn('document_id', array_column($jobs, 'id') ?: [0])->orderBy('sort_order')->get()->getResultArray() as $l) {
            $work[$l['document_id']][] = $l['description'];
        }
        $owner = $this->db->table('customers')->where('id', $v['customer_id'])->get()->getRowArray();
        return $this->ok([
            'id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'vin' => $v['vin'], 'variant' => $v['variant'], 'colour' => $v['colour'],
            'engine' => trim($v['engine_size'] . ' ' . $v['fuel_type']), 'transmission' => $v['transmission'], 'odometer' => $v['odometer'] ? (int) $v['odometer'] : null,
            'licence_expiry' => $v['licence_expiry'], 'next_service_date' => $v['next_service_date'], 'next_service_km' => $v['next_service_km'] ? (int) $v['next_service_km'] : null,
            'owner' => $this->isMechanic() ? null : ['id' => (int) $owner['id'], 'name' => customer_name($owner), 'phone' => $owner['phone']],
            'history' => array_map(fn ($j) => ['id' => (int) $j['id'], 'number' => $j['number'], 'date' => $j['created_at'], 'odometer' => $j['odometer_in'] ? (int) $j['odometer_in'] : null, 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status'], 'complaint' => $j['customer_complaint'], 'work' => array_slice($work[$j['id']] ?? [], 0, 8), 'total' => $this->isMechanic() ? null : (float) $j['total']], $jobs),
        ]);
    }

    public function createVehicle()
    {
        if ($r = $this->deny('vehicles')) {
            return $r;
        }
        $b = $this->body();
        $reg = strtoupper(trim((string) $this->val($b, 'registration', '')));
        $cid = (int) $this->val($b, 'customer_id', 0);
        if ($reg === '' || !$cid) {
            return $this->fail('Registration and owner are required.');
        }
        if ($this->db->table('vehicles')->where(['registration' => $reg, 'deleted_at' => null])->countAllResults()) {
            return $this->fail('A vehicle with registration ' . $reg . ' already exists.');
        }
        $this->db->table('vehicles')->insert([
            'customer_id' => $cid, 'registration' => $reg, 'vin' => $this->val($b, 'vin') ? strtoupper($this->val($b, 'vin')) : null, 'make' => $this->val($b, 'make'), 'model' => $this->val($b, 'model'),
            'year' => (int) $this->val($b, 'year', 0) ?: null, 'colour' => $this->val($b, 'colour'), 'odometer' => (int) $this->val($b, 'odometer', 0) ?: null,
            'engine_size' => $this->val($b, 'engine_size'), 'fuel_type' => $this->val($b, 'fuel_type'), 'transmission' => $this->val($b, 'transmission'),
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        return $this->ok(['id' => (int) $this->db->insertID()], 201);
    }

    // ------------------------------------------------------------ quotes & invoices

    public function quotes()
    {
        if ($r = $this->deny('quotes')) {
            return $r;
        }
        $b = $this->db->table('quotes q')->select('q.id, q.number, q.status, q.issue_date, q.expiry_date, q.total, c.first_name, c.last_name, c.company_name, c.type, v.registration')
            ->join('customers c', 'c.id = q.customer_id')->join('vehicles v', 'v.id = q.vehicle_id', 'left')->where('q.deleted_at', null);
        if ($s = $this->request->getGet('status')) {
            $b->where('q.status', $s);
        }
        return $this->ok(['items' => array_map(static fn ($q) => ['id' => (int) $q['id'], 'number' => $q['number'], 'status' => $q['status'], 'date' => $q['issue_date'], 'expires' => $q['expiry_date'], 'total' => (float) $q['total'], 'customer' => customer_name($q), 'registration' => $q['registration']], $b->orderBy('q.id', 'DESC')->limit(40)->get()->getResultArray())]);
    }

    public function quote(int $id)
    {
        if ($r = $this->deny('quotes')) {
            return $r;
        }
        $d = DocData::quote($id);
        if (!$d) {
            return $this->fail('Quote not found', 404);
        }
        $q = $d['quote'];
        return $this->ok([
            'id' => (int) $q['id'], 'number' => $q['number'], 'status' => $q['status'], 'date' => $q['issue_date'], 'expires' => $q['expiry_date'], 'notes' => $q['notes'],
            'customer' => ['id' => (int) $d['customer']['id'], 'name' => customer_name($d['customer']), 'phone' => $d['customer']['phone'], 'email' => $d['customer']['email']],
            'vehicle' => $d['vehicle'] ? ['registration' => $d['vehicle']['registration'], 'label' => $this->vehicleLabel($d['vehicle'])] : null,
            'items' => array_map(static fn ($i) => ['description' => $i['description'], 'type' => $i['item_type'], 'quantity' => (float) $i['quantity'], 'unit_price' => (float) $i['unit_price'], 'total' => (float) $i['line_total']], $d['items']),
            'totals' => ['subtotal' => (float) $q['subtotal'] - (float) $q['discount_total'], 'vat' => (float) $q['vat_total'], 'total' => (float) $q['total']],
            'public_url' => site_url('q/' . $q['public_token']), 'pdf_url' => site_url('q/' . $q['public_token'] . '/pdf'), 'job_card_id' => $q['job_card_id'] ? (int) $q['job_card_id'] : null,
        ]);
    }

    public function quoteAction(int $id, string $action)
    {
        if ($r = $this->deny('quotes')) {
            return $r;
        }
        if ($action === 'send') {
            return Billing::sendQuote($id, $this->val($this->body(), 'to')) ? $this->ok(['message' => 'Quote emailed.']) : $this->fail('Email failed. Check the customer email and SMTP settings.');
        }
        if ($action === 'convert') {
            return $this->ok(['job_card_id' => Billing::quoteToJobCard($id), 'message' => 'Job card created.']);
        }
        return $this->fail('Unknown action', 404);
    }

    public function invoices()
    {
        if ($r = $this->deny('invoices')) {
            return $r;
        }
        $b = $this->db->table('invoices i')->select('i.id, i.number, i.status, i.issue_date, i.due_date, i.total, i.balance_due, c.first_name, c.last_name, c.company_name, c.type, v.registration')
            ->join('customers c', 'c.id = i.customer_id')->join('vehicles v', 'v.id = i.vehicle_id', 'left')->where('i.deleted_at', null)->where('i.status !=', 'draft');
        $s = (string) $this->request->getGet('status');
        if ($s === 'outstanding') {
            $b->whereIn('i.status', ['unpaid', 'partial']);
        } elseif ($s === 'overdue') {
            $b->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date <', date('Y-m-d'));
        } elseif ($s) {
            $b->where('i.status', $s);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('i.number', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->orLike('v.registration', $q)->groupEnd();
        }
        return $this->ok(['items' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'number' => $i['number'], 'status' => invoice_display_status($i), 'date' => $i['issue_date'], 'due' => $i['due_date'], 'total' => (float) $i['total'], 'balance' => (float) $i['balance_due'], 'customer' => customer_name($i), 'registration' => $i['registration']], $b->orderBy('i.id', 'DESC')->limit(40)->get()->getResultArray())]);
    }

    public function invoice(int $id)
    {
        if ($r = $this->deny('invoices')) {
            return $r;
        }
        $d = DocData::invoice($id);
        if (!$d) {
            return $this->fail('Invoice not found', 404);
        }
        $i = $d['invoice'];
        $c = $d['customer'];
        $payUrl = site_url('pay/' . $i['public_token']);
        return $this->ok([
            'id' => (int) $i['id'], 'number' => $i['number'], 'status' => invoice_display_status($i), 'date' => $i['issue_date'], 'due' => $i['due_date'],
            'customer' => ['id' => (int) $c['id'], 'name' => customer_name($c), 'phone' => $c['phone'], 'email' => $c['email']],
            'vehicle' => $d['vehicle'] ? ['registration' => $d['vehicle']['registration'], 'label' => $this->vehicleLabel($d['vehicle'])] : null,
            'items' => array_map(static fn ($x) => ['description' => $x['description'], 'type' => $x['item_type'], 'quantity' => (float) $x['quantity'], 'unit_price' => (float) $x['unit_price'], 'total' => (float) $x['line_total']], $d['items']),
            'totals' => ['subtotal' => (float) $i['subtotal'] - (float) $i['discount_total'], 'vat' => (float) $i['vat_total'], 'total' => (float) $i['total'], 'paid' => (float) $i['amount_paid'], 'credited' => (float) $i['amount_credited'], 'balance' => (float) $i['balance_due']],
            'payments' => array_map(static fn ($p) => ['number' => $p['number'], 'date' => $p['paid_at'], 'method' => strtoupper($p['method']), 'amount' => (float) $p['amount']], $d['payments']),
            'pay_url' => $payUrl, 'pdf_url' => $payUrl . '/pdf',
            'whatsapp' => wa_link($c['phone'], 'Hi ' . customer_name($c) . ', your invoice ' . $i['number'] . ' from ' . setting('company_name') . ' for ' . money($i['balance_due']) . ' is ready. View and pay securely: ' . $payUrl),
            'methods' => \App\Controllers\Invoices::METHODS,
        ]);
    }

    public function invoiceAction(int $id, string $action)
    {
        if ($r = $this->deny('invoices')) {
            return $r;
        }
        $b = $this->body();
        switch ($action) {
            case 'send':
                return Billing::sendInvoice($id, $this->val($b, 'to')) ? $this->ok(['message' => 'Invoice emailed with pay link.']) : $this->fail('Email failed. Check the customer email and SMTP settings.');
            case 'remind':
                return Billing::sendInvoice($id, null, 'reminder') ? $this->ok(['message' => 'Reminder sent.']) : $this->fail('Reminder failed.');
            case 'payment':
                if (!can('payments')) {
                    return $this->fail('Not allowed', 403);
                }
                $inv = $this->db->table('invoices')->where('id', $id)->get()->getRowArray();
                $amount = round((float) $this->val($b, 'amount', 0), 2);
                $method = (string) $this->val($b, 'method', 'eft');
                if (!$inv || !in_array($inv['status'], ['unpaid', 'partial'], true) || $amount <= 0 || $amount - (float) $inv['balance_due'] > 0.009 || !array_key_exists($method, \App\Controllers\Invoices::METHODS)) {
                    return $this->fail('Enter an amount up to the balance (' . money($inv['balance_due'] ?? 0) . ').');
                }
                $pid = Billing::recordPayment($id, $amount, $method, ['reference' => $this->val($b, 'reference'), 'notify' => !empty($b['send_receipt'])]);
                $slip = $this->request->getFile('slip');
                if ($slip && $slip->isValid()) {
                    try {
                        Files::store($slip, 'payment', $pid, 'slip', 'Proof of payment');
                    } catch (\Throwable $e) {
                    }
                }
                return $this->ok(['message' => 'Payment of ' . money($amount) . ' recorded.', 'payment_id' => $pid]);
        }
        return $this->fail('Unknown action', 404);
    }

    // ------------------------------------------------------------ products & files

    public function products()
    {
        if (!can('inventory') && !can('job_cards')) {
            return $this->fail('Not allowed', 403);
        }
        $q = trim((string) $this->request->getGet('q'));
        $code = trim((string) $this->request->getGet('barcode'));
        $b = $this->db->table('products')->select('id, sku, barcode, name, type, category, sell_price, qty_on_hand, track_stock, bin_location, reorder_level')->where(['deleted_at' => null, 'is_active' => 1]);
        if ($code !== '') {
            $b->groupStart()->where('barcode', $code)->orWhere('sku', $code)->groupEnd();
        } elseif ($q !== '') {
            $b->groupStart()->like('name', $q)->orLike('sku', $q)->orLike('barcode', $q)->groupEnd();
        }
        if ($this->request->getGet('low')) {
            $b->where('track_stock', 1)->where('qty_on_hand <= reorder_level', null, false);
        }
        $rows = $b->orderBy('name')->limit(40)->get()->getResultArray();
        $prices = !$this->isMechanic();
        return $this->ok(['items' => array_map(static fn ($p) => ['id' => (int) $p['id'], 'sku' => $p['sku'], 'barcode' => $p['barcode'], 'name' => $p['name'], 'type' => $p['type'], 'category' => $p['category'],
            'price' => $prices ? (float) $p['sell_price'] : null, 'stock' => $p['track_stock'] ? (float) $p['qty_on_hand'] : null, 'low' => $p['track_stock'] && (float) $p['qty_on_hand'] <= (float) $p['reorder_level'], 'bin' => $p['bin_location']], $rows)]);
    }

    public function cannedJobs()
    {
        return $this->ok(['items' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'job_type' => $c['job_type'], 'hours' => (float) $c['labour_hours']], $this->db->table('canned_jobs')->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get()->getResultArray())]);
    }

    public function staff()
    {
        return $this->ok(['items' => array_map(static fn ($u) => ['id' => (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'role' => $u['role']], $this->db->table('users')->select('id, first_name, last_name, role')->where(['is_active' => 1, 'deleted_at' => null])->where('role !=', 'customer')->orderBy('first_name')->get()->getResultArray())]);
    }

    /** Stream an attachment the user may see (staff: any; customer: their own job photos). */
    public function file(int $id)
    {
        $f = $this->db->table('attachments')->where('id', $id)->get()->getRowArray();
        if (!$f || !is_file(Files::path($f))) {
            return $this->fail('Not found', 404);
        }
        if ($this->role() === 'customer') {
            $own = $f['entity_type'] === 'job_card' && $this->db->table('job_cards')->where(['id' => $f['entity_id'], 'customer_id' => (int) auth_user('customer_id')])->countAllResults();
            if (!$own) {
                return $this->fail('Not allowed', 403);
            }
        } elseif ($this->isMechanic() && $f['entity_type'] === 'job_card' && !$this->canSeeJob((int) $f['entity_id'])) {
            return $this->fail('Not allowed', 403);
        }
        return $this->response->setHeader('Content-Type', $f['mime'] ?: 'application/octet-stream')->setHeader('Cache-Control', 'private, max-age=86400')->setBody((string) file_get_contents(Files::path($f)));
    }
}
