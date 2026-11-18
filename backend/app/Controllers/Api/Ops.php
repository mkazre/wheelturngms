<?php

namespace App\Controllers\Api;

use App\Controllers\Dashboard;
use App\Libraries\AiAssistant;
use App\Libraries\Bookings;
use App\Libraries\Notify;
use App\Libraries\Reports;
use App\Libraries\Settings as S;

/** Profile, push tokens, notifications, dashboard, diary, bookings, tasks, AI. */
class Ops extends BaseApi
{
    public function me()
    {
        $u = auth_user();
        $modules = ['dashboard', 'workshop', 'job_cards', 'quotes', 'invoices', 'payments', 'customers', 'vehicles', 'inventory', 'reports', 'accounting'];
        return $this->ok([
            'user' => ['id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'email' => $u['email'], 'phone' => $u['phone'], 'role' => $u['role'],
                'role_label' => config('Permissions')->labels[$u['role']] ?? $u['role'], 'customer_id' => $u['customer_id'] ? (int) $u['customer_id'] : null, 'two_factor' => (bool) $u['totp_enabled']],
            'mode' => $u['role'] === 'customer' ? 'customer' : ($u['role'] === 'mechanic' ? 'technician' : 'manager'),
            'can' => array_combine($modules, array_map('can', $modules)),
            'company' => ['name' => S::get('company_name'), 'phone' => S::get('company_phone'), 'email' => S::get('company_email'), 'address' => S::get('company_address'), 'logo' => base_url('assets/img/logo-sm.png'), 'web' => site_url(), 'booking_url' => site_url('book')],
            'ai' => AiAssistant::enabled(),
            'unread' => Notify::unread((int) $u['id'], (string) $u['role']),
        ]);
    }

    public function changePassword()
    {
        $b = $this->body();
        $u = auth_user();
        if (!password_verify((string) ($b['current'] ?? ''), $u['password_hash'])) {
            return $this->fail('Your current password is incorrect.');
        }
        $new = (string) ($b['new'] ?? '');
        if (strlen($new) < 8) {
            return $this->fail('Use at least 8 characters.');
        }
        $this->db->table('users')->where('id', $u['id'])->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'updated_at' => $this->now()]);
        return $this->ok(['message' => 'Password changed.']);
    }

    public function pushToken()
    {
        $token = (string) $this->val($this->body(), 'token', '');
        if ($token !== '' && !str_starts_with($token, 'ExponentPushToken[')) {
            return $this->fail('Invalid push token.');
        }
        $this->db->table('users')->where('id', $this->uid())->update(['push_token' => $token ?: null]);
        return $this->ok(['ok' => true]);
    }

    public function notifications()
    {
        $uid = $this->uid();
        $seen = (string) S::get('notif_seen_' . $uid, '1970-01-01 00:00:00');
        $rows = Notify::forUser($uid, $this->role(), 40);
        S::set('notif_seen_' . $uid, $this->now(), 'system');
        return $this->ok(['items' => array_map(static fn ($n) => ['id' => (int) $n['id'], 'title' => $n['title'], 'body' => $n['body'], 'url' => $n['url'], 'icon' => $n['icon'], 'date' => $n['created_at'], 'unread' => $n['created_at'] > $seen], $rows)]);
    }

    public function dashboard()
    {
        if ($r = $this->deny('dashboard')) {
            return $r;
        }
        $period = (string) ($this->request->getGet('period') ?: 'mtd');
        $period = array_key_exists($period, Dashboard::PERIODS) ? $period : 'mtd';
        [$from, $to, $pFrom, $pTo, $bucket] = Dashboard::periodRange($period);
        $now = Reports::summary($from, $to);
        $prev = Reports::summary($pFrom, $pTo);
        $series = Reports::series($from, $to, $bucket);
        $delta = static fn ($k) => Reports::delta((float) $now[$k], (float) $prev[$k]);
        $outstanding = (float) $this->db->table('invoices')->selectSum('balance_due')->where('deleted_at', null)->whereIn('status', ['unpaid', 'partial'])->get()->getRow()->balance_due;
        $overdue = (float) $this->db->table('invoices')->selectSum('balance_due')->where('deleted_at', null)->whereIn('status', ['unpaid', 'partial'])->where('due_date <', date('Y-m-d'))->get()->getRow()->balance_due;
        $target = (float) S::get('monthly_revenue_target', 0);
        return $this->ok([
            'period' => $period, 'periods' => Dashboard::PERIODS, 'from' => $from, 'to' => $to,
            'kpis' => [
                ['key' => 'revenue', 'label' => 'Revenue (excl. VAT)', 'value' => $now['revenue'], 'money' => true, 'delta' => $delta('revenue')],
                ['key' => 'collected', 'label' => 'Cash collected', 'value' => $now['collected'], 'money' => true, 'delta' => $delta('collected')],
                ['key' => 'net_profit', 'label' => 'Net profit', 'value' => $now['net_profit'], 'money' => true, 'delta' => $delta('net_profit')],
                ['key' => 'outstanding', 'label' => 'Owed to us', 'value' => $outstanding, 'money' => true, 'hint' => money($overdue) . ' overdue'],
                ['key' => 'jobs', 'label' => 'Jobs completed', 'value' => $now['jobs_completed'], 'money' => false, 'delta' => $delta('jobs_completed')],
                ['key' => 'aro', 'label' => 'Average invoice', 'value' => $now['avg_invoice'], 'money' => true, 'delta' => $delta('avg_invoice')],
            ],
            'margin' => round($now['gross_margin'], 1),
            'series' => $series,
            'mix' => Reports::revenueByType($from, $to),
            'methods' => Reports::paymentsByMethod($from, $to),
            'pipeline' => Reports::jobsByStatus(),
            'technicians' => array_slice(Reports::mechanicProductivity($from, $to), 0, 5),
            'target' => $target > 0 ? ['target' => $target, 'mtd' => Reports::summary(date('Y-m-01'), date('Y-m-d'))['revenue'], 'day_pct' => round((int) date('j') / (int) date('t') * 100)] : null,
            'pending_bookings' => $this->db->table('bookings')->where('status', 'pending')->countAllResults(),
            'low_stock' => count(Reports::lowStock(100)),
        ]);
    }

    /** Diary: jobs and bookings between two dates. */
    public function diary()
    {
        if ($r = $this->deny('workshop')) {
            return $r;
        }
        $from = substr((string) ($this->request->getGet('from') ?: date('Y-m-d')), 0, 10);
        $to = substr((string) ($this->request->getGet('to') ?: date('Y-m-d', strtotime($from . ' +6 days'))), 0, 10);
        $jobs = $this->db->table('job_cards j')->select('j.id, j.number, j.status, j.booked_for, j.promised_at, j.job_type, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')->where('j.deleted_at', null)->where('j.status !=', 'cancelled')
            ->where('j.booked_for >=', $from . ' 00:00:00')->where('j.booked_for <=', $to . ' 23:59:59')->orderBy('j.booked_for')->get()->getResultArray();
        $bookings = $this->db->table('bookings')->where('status', 'pending')->orderBy('preferred_at')->get()->getResultArray();
        return $this->ok([
            'jobs' => array_map(fn ($j) => ['id' => (int) $j['id'], 'number' => $j['number'], 'at' => $j['booked_for'], 'until' => $j['promised_at'], 'status_label' => job_statuses()[$j['status']]['label'] ?? $j['status'], 'status_color' => job_statuses()[$j['status']]['hex'] ?? '#64748b', 'registration' => $j['registration'], 'vehicle' => trim($j['make'] . ' ' . $j['model']), 'customer' => $this->isMechanic() ? null : customer_name($j), 'job_type' => $j['job_type']], $jobs),
            'requests' => $this->isMechanic() ? [] : array_map(static fn ($b) => ['id' => (int) $b['id'], 'name' => $b['name'], 'phone' => $b['phone'], 'email' => $b['email'], 'registration' => $b['registration'], 'vehicle' => $b['vehicle_desc'], 'service' => $b['service'], 'at' => $b['preferred_at'], 'notes' => $b['notes'], 'created_at' => $b['created_at']], $bookings),
        ]);
    }

    public function booking(int $id, string $action)
    {
        if ($this->isMechanic() || !can('workshop')) {
            return $this->fail('Not allowed', 403);
        }
        if ($action === 'confirm') {
            $jobId = Bookings::confirm($id);
            return $jobId ? $this->ok(['job_card_id' => $jobId, 'message' => 'Booking confirmed and job card created.']) : $this->fail('Booking not found.', 404);
        }
        if ($action === 'decline') {
            $this->db->table('bookings')->where('id', $id)->update(['status' => 'declined', 'updated_at' => $this->now()]);
            return $this->ok(['message' => 'Booking declined.']);
        }
        return $this->fail('Unknown action', 404);
    }

    public function tasks()
    {
        $rows = $this->db->table('tasks t')->select('t.*, a.first_name assignee')->join('users a', 'a.id = t.assigned_to', 'left')
            ->where(['t.assigned_to' => $this->uid(), 't.status' => 'open'])->orderBy('t.due_at IS NULL', '', false)->orderBy('t.due_at')->get()->getResultArray();
        return $this->ok(['items' => array_map(static fn ($t) => ['id' => (int) $t['id'], 'title' => $t['title'], 'notes' => $t['notes'], 'due_at' => $t['due_at'], 'priority' => $t['priority'], 'overdue' => $t['due_at'] && strtotime($t['due_at']) < time(), 'entity_type' => $t['entity_type'], 'entity_id' => $t['entity_id'] ? (int) $t['entity_id'] : null], $rows)]);
    }

    public function createTask()
    {
        $b = $this->body();
        $title = trim((string) $this->val($b, 'title', ''));
        if ($title === '') {
            return $this->fail('Task title is required.');
        }
        $assignee = (int) $this->val($b, 'assigned_to', 0) ?: $this->uid();
        $this->db->table('tasks')->insert(['title' => mb_substr($title, 0, 255), 'notes' => $this->val($b, 'notes'), 'due_at' => $this->val($b, 'due_at') ? date('Y-m-d H:i:s', strtotime($this->val($b, 'due_at'))) : null,
            'assigned_to' => $assignee, 'entity_type' => $this->val($b, 'entity_type'), 'entity_id' => (int) $this->val($b, 'entity_id', 0) ?: null, 'status' => 'open', 'priority' => $this->val($b, 'priority', 'normal'),
            'created_by' => $this->uid(), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        if ($assignee !== $this->uid()) {
            Notify::staff('New task: ' . $title, 'From ' . auth_user('first_name'), 'tasks', 'clipboard', $assignee);
        }
        return $this->ok(['id' => (int) $this->db->insertID()], 201);
    }

    public function completeTask(int $id)
    {
        $this->db->table('tasks')->where('id', $id)->update(['status' => 'done', 'completed_at' => $this->now(), 'updated_at' => $this->now()]);
        return $this->ok(['ok' => true]);
    }

    // ------------------------------------------------------------ AI

    public function aiAsk()
    {
        if (!can('job_cards')) {
            return $this->fail('Not allowed', 403);
        }
        $b = $this->body();
        $messages = array_values(array_filter(array_map(static fn ($m) => ['role' => ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr(trim((string) ($m['content'] ?? '')), 0, 4000)], (array) ($b['messages'] ?? [])), static fn ($m) => $m['content'] !== ''));
        if (!$messages || end($messages)['role'] !== 'user') {
            return $this->fail('Ask a question.');
        }
        $context = null;
        if ($jobId = (int) $this->val($b, 'job_id', 0)) {
            if (!$this->canSeeJob($jobId)) {
                return $this->fail('Not allowed', 403);
            }
            $d = \App\Libraries\DocData::jobCard($jobId);
            $context = "The technician is working on this job:\n" . AiAssistant::jobContext($d['job'], $d['vehicle'], $d['inspections']);
        }
        try {
            $answer = AiAssistant::ask(array_slice($messages, -20), 'medium', 4000, $context);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 503);
        }
        return $this->ok(['answer' => $answer]);
    }

    public function aiFaultCodes()
    {
        if (!can('job_cards')) {
            return $this->fail('Not allowed', 403);
        }
        $b = $this->body();
        $codes = strtoupper(trim((string) $this->val($b, 'codes', '')));
        if (!preg_match('/^[A-Z0-9 ,\-\/]{4,200}$/', $codes)) {
            return $this->fail('Enter fault codes like P0301, P0171.');
        }
        try {
            return $this->ok(['answer' => AiAssistant::explainFaultCodes($codes, mb_substr((string) $this->val($b, 'vehicle', 'light vehicle'), 0, 120))]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 503);
        }
    }

    public function decodeVin()
    {
        $c = new \App\Controllers\Vehicles();
        $c->initController($this->request, $this->response, service('logger'));
        return $c->decodeVin();
    }
}
