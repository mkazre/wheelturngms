<?php

namespace App\Controllers\Api;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\DocData;
use App\Libraries\LineItems;
use App\Libraries\Notify;

/** Customer app: their vehicles, live jobs, approvals, quotes, invoices and bookings. Everything is scoped to the signed-in customer. */
class My extends BaseApi
{
    private function cid(): int
    {
        return (int) auth_user('customer_id');
    }

    private function guard()
    {
        return $this->role() === 'customer' && $this->cid() ? null : $this->fail('Customer account required.', 403);
    }

    public function overview()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $cid = $this->cid();
        $vehicles = $this->db->table('vehicles')->where(['customer_id' => $cid, 'deleted_at' => null])->get()->getResultArray();
        $active = $this->db->table('job_cards j')->select('j.id, j.number, j.status, j.promised_at, j.created_at, v.registration, v.make, v.model')
            ->join('vehicles v', 'v.id = j.vehicle_id')->where(['j.customer_id' => $cid, 'j.deleted_at' => null])->whereNotIn('j.status', ['collected', 'cancelled'])->orderBy('j.id', 'DESC')->get()->getResultArray();
        $approvals = (int) $this->db->table('inspections i')->join('job_cards j', 'j.id = i.job_card_id')->where(['j.customer_id' => $cid, 'j.deleted_at' => null])
            ->whereIn('i.status', ['advisory', 'urgent'])->where('i.customer_decision', null)->where('i.estimate >', 0)->whereNotIn('j.status', ['invoiced', 'collected', 'cancelled'])->countAllResults();
        $owed = (float) $this->db->table('invoices')->selectSum('balance_due')->where(['customer_id' => $cid, 'deleted_at' => null])->whereIn('status', ['unpaid', 'partial'])->get()->getRow()->balance_due;
        $openQuotes = $this->db->table('quotes')->where(['customer_id' => $cid, 'deleted_at' => null, 'status' => 'sent'])->countAllResults();
        return $this->ok([
            'vehicles' => array_map(fn ($v) => ['id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'next_service_date' => $v['next_service_date'], 'licence_expiry' => $v['licence_expiry'], 'odometer' => $v['odometer'] ? (int) $v['odometer'] : null], $vehicles),
            'active_jobs' => array_map(static fn ($j) => ['id' => (int) $j['id'], 'number' => $j['number'], 'status' => $j['status'], 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status'], 'status_color' => job_statuses()[$j['status']]['hex'] ?? '#64748b', 'promised_at' => $j['promised_at'], 'registration' => $j['registration'], 'vehicle' => trim($j['make'] . ' ' . $j['model'])], $active),
            'approvals_waiting' => $approvals, 'amount_owed' => $owed, 'open_quotes' => $openQuotes,
        ]);
    }

    public function job(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $d = DocData::jobCard($id);
        if (!$d || (int) $d['job']['customer_id'] !== $this->cid()) {
            return $this->fail('Not found', 404);
        }
        $j = $d['job'];
        $flow = ['booked' => 'Booked', 'checked_in' => 'Checked in', 'in_progress' => 'Being worked on', 'quality_check' => 'Quality check', 'completed' => 'Ready for collection', 'collected' => 'Collected'];
        $cur = $j['status'] === 'invoiced' ? 'completed' : (in_array($j['status'], ['awaiting_parts', 'awaiting_approval'], true) ? 'in_progress' : $j['status']);
        $idx = array_search($cur, array_keys($flow), true);
        $timeline = $this->db->table('activity_logs')->select('description, created_at')->where(['entity_type' => 'job_card', 'entity_id' => $id])->whereIn('action', ['created', 'status', 'invoiced'])->orderBy('id', 'DESC')->get()->getResultArray();
        $photos = array_values(array_filter(Files::for('job_card', $id), static fn ($f) => Files::isImage($f) || str_starts_with((string) $f['mime'], 'video/')));
        $invoice = $this->db->table('invoices')->select('id, number, total, balance_due, status, public_token')->where(['job_card_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->get()->getRowArray();
        return $this->ok([
            'id' => (int) $j['id'], 'number' => $j['number'], 'status' => $j['status'], 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status'], 'status_color' => job_statuses()[$j['status']]['hex'] ?? '#64748b',
            'promised_at' => $j['promised_at'], 'complaint' => $j['customer_complaint'], 'work_performed' => $j['work_performed'], 'recommendations' => $j['recommendations'],
            'vehicle' => ['registration' => $d['vehicle']['registration'], 'label' => $this->vehicleLabel($d['vehicle'])],
            'steps' => array_map(static fn ($k, $l) => ['key' => $k, 'label' => $l], array_keys($flow), $flow), 'step_index' => $idx === false ? 0 : $idx,
            'findings' => array_values(array_map(static fn ($i) => ['id' => (int) $i['id'], 'item' => $i['item'], 'note' => $i['note'], 'severity' => $i['status'], 'estimate' => (float) $i['estimate'], 'decision' => $i['customer_decision']],
                array_filter($d['inspections'], static fn ($i) => in_array($i['status'], ['advisory', 'urgent'], true)))),
            'can_decide' => !in_array($j['status'], ['invoiced', 'collected', 'cancelled'], true),
            'photos' => array_map(fn ($f) => ['id' => (int) $f['id'], 'url' => $this->fileUrl((int) $f['id']), 'is_video' => str_starts_with((string) $f['mime'], 'video/')], $photos),
            'timeline' => $timeline,
            'invoice' => $invoice ? ['id' => (int) $invoice['id'], 'number' => $invoice['number'], 'total' => (float) $invoice['total'], 'balance' => (float) $invoice['balance_due'], 'status' => $invoice['status'], 'pay_url' => site_url('pay/' . $invoice['public_token'])] : null,
            'tracker_url' => site_url('track/' . $j['public_token']), 'vat_rate' => (float) setting('vat_rate', 15),
        ]);
    }

    /** Approve / decline an inspection finding (same rules as the web tracker). */
    public function decide(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $job = $this->db->table('job_cards')->where(['id' => $id, 'customer_id' => $this->cid(), 'deleted_at' => null])->get()->getRowArray();
        if (!$job || in_array($job['status'], ['invoiced', 'collected', 'cancelled'], true)) {
            return $this->fail('This job is closed. Please call us to book the work.');
        }
        $b = $this->body();
        $item = $this->db->table('inspections')->where(['id' => (int) $this->val($b, 'item_id', 0), 'job_card_id' => $id])->get()->getRowArray();
        if (!$item || $item['customer_decision']) {
            return $this->fail('Already decided.');
        }
        $decision = $this->val($b, 'decision') === 'approve' ? 'approved' : 'declined';
        $this->db->transStart();
        $this->db->table('inspections')->where('id', $item['id'])->update(['customer_decision' => $decision, 'decided_at' => $this->now()]);
        if ($decision === 'approved' && (float) $item['estimate'] > 0) {
            $max = (int) $this->db->table('line_items')->selectMax('sort_order')->where(['document_type' => 'job_card', 'document_id' => $id])->get()->getRow()->sort_order;
            $this->db->table('line_items')->insert(LineItems::calc(['item_type' => 'sundry', 'description' => 'Approved: ' . $item['item'] . ($item['note'] ? ' (' . $item['note'] . ')' : ''), 'quantity' => 1, 'unit_price' => (float) $item['estimate'], 'vat_rate' => setting('vat_rate', 15)])
                + ['document_type' => 'job_card', 'document_id' => $id, 'sort_order' => $max + 1, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            LineItems::recalc('job_card', $id);
        }
        $this->db->transComplete();
        Activity::log('job_card', $id, 'customer_' . $decision, 'Customer ' . $decision . ' in the app: ' . $item['item']);
        Notify::staff('Customer ' . $decision . ' work on ' . $job['number'], $item['item'], 'job-cards/' . $id, $decision === 'approved' ? 'check' : 'x');
        return $this->ok(['message' => $decision === 'approved' ? 'Approved. We will add it to your job.' : 'Noted. Declined.']);
    }

    public function quotes()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $rows = $this->db->table('quotes q')->select('q.id, q.number, q.status, q.issue_date, q.expiry_date, q.total, q.public_token, v.registration')->join('vehicles v', 'v.id = q.vehicle_id', 'left')
            ->where(['q.customer_id' => $this->cid(), 'q.deleted_at' => null])->whereNotIn('q.status', ['draft'])->orderBy('q.id', 'DESC')->limit(30)->get()->getResultArray();
        return $this->ok(['items' => array_map(static fn ($q) => ['id' => (int) $q['id'], 'number' => $q['number'], 'status' => $q['status'], 'date' => $q['issue_date'], 'expires' => $q['expiry_date'], 'total' => (float) $q['total'], 'registration' => $q['registration'], 'url' => site_url('q/' . $q['public_token']), 'pdf_url' => site_url('q/' . $q['public_token'] . '/pdf')], $rows)]);
    }

    public function quote(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $d = DocData::quote($id);
        if (!$d || (int) $d['quote']['customer_id'] !== $this->cid()) {
            return $this->fail('Not found', 404);
        }
        $q = $d['quote'];
        return $this->ok(['id' => (int) $q['id'], 'number' => $q['number'], 'status' => $q['status'], 'date' => $q['issue_date'], 'expires' => $q['expiry_date'], 'notes' => $q['notes'],
            'vehicle' => $d['vehicle'] ? $d['vehicle']['registration'] . ' · ' . $this->vehicleLabel($d['vehicle']) : null,
            'items' => array_map(static fn ($i) => ['description' => $i['description'], 'quantity' => (float) $i['quantity'], 'total' => (float) $i['line_total']], $d['items']),
            'totals' => ['subtotal' => (float) $q['subtotal'] - (float) $q['discount_total'], 'vat' => (float) $q['vat_total'], 'total' => (float) $q['total']],
            'can_respond' => in_array($q['status'], ['draft', 'sent'], true), 'pdf_url' => site_url('q/' . $q['public_token'] . '/pdf')]);
    }

    public function respondQuote(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $q = $this->db->table('quotes')->where(['id' => $id, 'customer_id' => $this->cid(), 'deleted_at' => null])->get()->getRowArray();
        if (!$q || !in_array($q['status'], ['draft', 'sent'], true)) {
            return $this->fail('This quote can no longer be changed.');
        }
        $accept = $this->val($this->body(), 'decision') === 'accept';
        $this->db->table('quotes')->where('id', $id)->update(['status' => $accept ? 'accepted' : 'declined', ($accept ? 'accepted_at' : 'declined_at') => $this->now(), 'updated_at' => $this->now()]);
        Activity::log('quote', $id, $accept ? 'accepted' : 'declined', 'Customer ' . ($accept ? 'accepted' : 'declined') . ' the quote in the app');
        Notify::staff('Quote ' . $q['number'] . ($accept ? ' accepted' : ' declined') . ' in the app', money($q['total']), 'quotes/' . $id, $accept ? 'check' : 'x');
        return $this->ok(['message' => $accept ? 'Quote accepted. We will contact you to book your vehicle in.' : 'Quote declined.']);
    }

    public function invoices()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $rows = $this->db->table('invoices i')->select('i.id, i.number, i.status, i.issue_date, i.due_date, i.total, i.balance_due, i.public_token, v.registration')->join('vehicles v', 'v.id = i.vehicle_id', 'left')
            ->where(['i.customer_id' => $this->cid(), 'i.deleted_at' => null])->whereNotIn('i.status', ['draft', 'void'])->orderBy('i.id', 'DESC')->limit(40)->get()->getResultArray();
        return $this->ok(['items' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'number' => $i['number'], 'status' => invoice_display_status($i), 'date' => $i['issue_date'], 'due' => $i['due_date'], 'total' => (float) $i['total'], 'balance' => (float) $i['balance_due'], 'registration' => $i['registration'], 'pay_url' => site_url('pay/' . $i['public_token']), 'pdf_url' => site_url('pay/' . $i['public_token'] . '/pdf')], $rows)]);
    }

    public function vehicle(int $id)
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $v = $this->db->table('vehicles')->where(['id' => $id, 'customer_id' => $this->cid(), 'deleted_at' => null])->get()->getRowArray();
        if (!$v) {
            return $this->fail('Not found', 404);
        }
        $jobs = $this->db->table('job_cards')->select('id, number, status, created_at, odometer_in, work_performed, customer_complaint')->where(['vehicle_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['cancelled'])->orderBy('id', 'DESC')->get()->getResultArray();
        return $this->ok(['id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'odometer' => $v['odometer'] ? (int) $v['odometer'] : null,
            'next_service_date' => $v['next_service_date'], 'next_service_km' => $v['next_service_km'] ? (int) $v['next_service_km'] : null, 'licence_expiry' => $v['licence_expiry'],
            'history' => array_map(static fn ($j) => ['id' => (int) $j['id'], 'number' => $j['number'], 'date' => $j['created_at'], 'odometer' => $j['odometer_in'] ? (int) $j['odometer_in'] : null, 'summary' => $j['work_performed'] ?: $j['customer_complaint'], 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status']], $jobs)]);
    }

    /** Booking request from the app (linked to the customer's account and vehicle). */
    public function book()
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $b = $this->body();
        $at = (string) $this->val($b, 'preferred_at', '');
        if (!$at || strtotime($at) < time()) {
            return $this->fail('Choose a date and time in the future.');
        }
        if ((int) date('N', strtotime($at)) === 7) {
            return $this->fail('We are closed on Sundays. Please choose another day.');
        }
        $c = $this->db->table('customers')->where('id', $this->cid())->get()->getRowArray();
        $v = ($vid = (int) $this->val($b, 'vehicle_id', 0)) ? $this->db->table('vehicles')->where(['id' => $vid, 'customer_id' => $this->cid()])->get()->getRowArray() : null;
        $this->db->table('bookings')->insert([
            'customer_id' => $this->cid(), 'vehicle_id' => $v['id'] ?? null, 'name' => customer_name($c), 'phone' => $c['phone'], 'email' => $c['email'],
            'registration' => $v['registration'] ?? null, 'vehicle_desc' => $v ? trim($v['make'] . ' ' . $v['model']) : null, 'service' => mb_substr((string) $this->val($b, 'service', 'Service'), 0, 150),
            'preferred_at' => date('Y-m-d H:i:s', strtotime($at)), 'notes' => $this->val($b, 'notes'), 'status' => 'pending', 'source' => 'app', 'token' => bin2hex(random_bytes(16)),
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        Notify::staff('New app booking: ' . customer_name($c), ($this->val($b, 'service') ?: 'Service') . ' · ' . fdatetime($at), 'diary', 'calendar');
        return $this->ok(['message' => 'Booking request sent. We will confirm shortly.'], 201);
    }

    public function services()
    {
        $names = array_column($this->db->table('canned_jobs')->select('name')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(), 'name');
        return $this->ok(['items' => array_values(array_unique(array_merge($names, \App\Controllers\JobCards::JOB_TYPES)))]);
    }
}
