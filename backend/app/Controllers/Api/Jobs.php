<?php

namespace App\Controllers\Api;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\JobCosting;
use App\Libraries\JobWorkflow;
use App\Libraries\LineItems;
use App\Libraries\Numbering;

/** Job cards for managers, advisors and technicians. */
class Jobs extends BaseApi
{
    public const OPEN = ['booked', 'checked_in', 'in_progress', 'awaiting_approval', 'awaiting_parts', 'quality_check', 'completed'];

    private function summary(array $r, array $mechs = [], array $running = []): array
    {
        $s = job_statuses()[$r['status']] ?? ['label' => $r['status'], 'hex' => '#64748b'];
        return [
            'id' => (int) $r['id'], 'number' => $r['number'], 'status' => $r['status'], 'status_label' => $s['label'], 'status_color' => $s['hex'],
            'priority' => $r['priority'], 'job_type' => $r['job_type'], 'bay' => $r['bay'] ?? null,
            'registration' => $r['registration'], 'vehicle' => trim(($r['make'] ?? '') . ' ' . ($r['model'] ?? '')),
            'customer' => $this->isMechanic() ? null : customer_name($r),
            'complaint' => $r['customer_complaint'] ?? null,
            'booked_for' => $r['booked_for'], 'promised_at' => $r['promised_at'],
            'late' => $r['promised_at'] && strtotime($r['promised_at']) < time() && !in_array($r['status'], ['completed', 'invoiced', 'collected', 'cancelled'], true),
            'total' => $this->isMechanic() ? null : (float) $r['total'],
            'mechanics' => $mechs[$r['id']] ?? [], 'running' => isset($running[$r['id']]),
        ];
    }

    private function listQuery()
    {
        $b = $this->db->table('job_cards j')
            ->select('j.id, j.number, j.status, j.priority, j.job_type, j.bay, j.booked_for, j.promised_at, j.total, j.customer_complaint, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')->where('j.deleted_at', null);
        if ($this->isMechanic() || $this->request->getGet('mine')) {
            $b->where('j.id IN (SELECT job_card_id FROM job_card_mechanics WHERE user_id = ' . $this->uid() . ')', null, false);
        }
        return $b;
    }

    private function decorate(array $rows): array
    {
        $ids = array_column($rows, 'id') ?: [0];
        $mechs = [];
        foreach ($this->db->table('job_card_mechanics jm')->select('jm.job_card_id, u.id, u.first_name, u.last_name')->join('users u', 'u.id = jm.user_id')->whereIn('jm.job_card_id', $ids)->get()->getResultArray() as $m) {
            $mechs[$m['job_card_id']][] = ['id' => (int) $m['id'], 'name' => trim($m['first_name'] . ' ' . $m['last_name']), 'initials' => initials($m['first_name'] . ' ' . $m['last_name'])];
        }
        $running = array_column($this->db->table('time_logs')->select('job_card_id')->where('ended_at', null)->whereIn('job_card_id', $ids)->get()->getResultArray(), 'job_card_id', 'job_card_id');
        return array_map(fn ($r) => $this->summary($r, $mechs, $running), $rows);
    }

    public function index()
    {
        if ($r = $this->deny('job_cards')) {
            return $r;
        }
        $b = $this->listQuery();
        $status = (string) $this->request->getGet('status');
        if ($status === '' || $status === 'open') {
            $b->whereIn('j.status', self::OPEN);
        } elseif ($status !== 'all') {
            $b->where('j.status', $status);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('j.number', $q)->orLike('v.registration', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->groupEnd();
        }
        $page = max(1, (int) $this->request->getGet('page'));
        $rows = $b->orderBy('j.id', 'DESC')->limit(30, ($page - 1) * 30)->get()->getResultArray();
        return $this->ok(['items' => $this->decorate($rows), 'page' => $page, 'more' => count($rows) === 30]);
    }

    /** Workshop board: open jobs grouped by status column. */
    public function board()
    {
        if ($r = $this->deny('workshop')) {
            return $r;
        }
        $rows = $this->listQuery()->whereIn('j.status', self::OPEN)->orderBy('j.promised_at')->get()->getResultArray();
        $cols = [];
        foreach (self::OPEN as $s) {
            $cols[$s] = ['status' => $s, 'label' => job_statuses()[$s]['label'], 'color' => job_statuses()[$s]['hex'], 'jobs' => []];
        }
        foreach ($this->decorate($rows) as $j) {
            $cols[$j['status']]['jobs'][] = $j;
        }
        return $this->ok(['columns' => array_values($cols)]);
    }

    public function show(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('This job is not assigned to you.', 403);
        }
        $d = DocData::jobCard($id);
        if (!$d) {
            return $this->fail('Job not found', 404);
        }
        $job = $d['job'];
        $prices = !$this->isMechanic();
        $statuses = [];
        foreach (job_statuses() as $k => $s) {
            if (!$this->isMechanic() || !in_array($k, ['invoiced', 'collected', 'cancelled'], true)) {
                $statuses[] = ['value' => $k, 'label' => $s['label'], 'color' => $s['hex']];
            }
        }
        $files = array_map(fn ($f) => ['id' => (int) $f['id'], 'url' => $this->fileUrl((int) $f['id']), 'name' => $f['original_name'], 'mime' => $f['mime'], 'category' => $f['category'], 'is_video' => str_starts_with((string) $f['mime'], 'video/'), 'is_image' => Files::isImage($f), 'created_at' => $f['created_at']], Files::for('job_card', $id));
        $time = $this->db->table('time_logs t')->select('t.id, t.started_at, t.ended_at, t.minutes, u.first_name')->join('users u', 'u.id = t.user_id')->where('t.job_card_id', $id)->orderBy('t.id', 'DESC')->get()->getResultArray();
        $running = $this->db->table('time_logs')->where(['job_card_id' => $id, 'user_id' => $this->uid(), 'ended_at' => null])->get()->getRowArray();
        $invoice = $this->db->table('invoices')->select('id, number, total, balance_due, status')->where(['job_card_id' => $id, 'deleted_at' => null])->where('status !=', 'void')->get()->getRowArray();
        $c = $d['customer'];
        $v = $d['vehicle'];
        return $this->ok([
            'id' => (int) $job['id'], 'number' => $job['number'], 'status' => $job['status'], 'status_label' => job_statuses()[$job['status']]['label'] ?? $job['status'],
            'status_color' => job_statuses()[$job['status']]['hex'] ?? '#64748b', 'statuses' => $statuses,
            'priority' => $job['priority'], 'job_type' => $job['job_type'], 'bay' => $job['bay'], 'fuel_level' => $job['fuel_level'],
            'odometer_in' => $job['odometer_in'] ? (int) $job['odometer_in'] : null, 'booked_for' => $job['booked_for'], 'promised_at' => $job['promised_at'],
            'checked_in_at' => $job['checked_in_at'], 'completed_at' => $job['completed_at'],
            'customer_complaint' => $job['customer_complaint'], 'diagnosis' => $job['diagnosis'], 'work_performed' => $job['work_performed'],
            'recommendations' => $job['recommendations'], 'internal_notes' => $prices ? $job['internal_notes'] : null,
            'vehicle' => $v ? ['id' => (int) $v['id'], 'registration' => $v['registration'], 'label' => $this->vehicleLabel($v), 'vin' => $v['vin'], 'colour' => $v['colour'], 'engine' => trim($v['engine_size'] . ' ' . $v['fuel_type']), 'transmission' => $v['transmission']] : null,
            'customer' => $prices ? ['id' => (int) $c['id'], 'name' => customer_name($c), 'phone' => $c['phone'], 'email' => $c['email']] : ['name' => $c['first_name']],
            'mechanics' => array_map(static fn ($m) => ['id' => (int) $m['id'], 'name' => trim($m['first_name'] . ' ' . $m['last_name'])], $d['mechanics']),
            'items' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'type' => $i['item_type'], 'description' => $i['description'], 'quantity' => (float) $i['quantity'],
                'unit_price' => $prices ? (float) $i['unit_price'] : null, 'total' => $prices ? (float) $i['line_total'] : null, 'mechanic' => $i['mechanic_name']], $d['items']),
            'totals' => $prices ? ['subtotal' => (float) $job['subtotal'] - (float) $job['discount_total'], 'vat' => (float) $job['vat_total'], 'total' => (float) $job['total']] : null,
            'inspections' => array_map(static fn ($i) => ['id' => (int) $i['id'], 'section' => $i['section'] ?: 'General', 'item' => $i['item'], 'status' => $i['status'], 'note' => $i['note'], 'estimate' => (float) $i['estimate'], 'decision' => $i['customer_decision']], $d['inspections']),
            'checklist' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'item' => $c['item'], 'done' => (bool) $c['is_done']], $this->db->table('job_checklist_items')->where('job_card_id', $id)->orderBy('sort_order')->orderBy('id')->get()->getResultArray()),
            'damage' => json_decode((string) $job['damage_marks'], true) ?: (object) [],
            'files' => $files,
            'time' => ['logs' => $time, 'total_minutes' => array_sum(array_column($time, 'minutes')), 'running_since' => $running['started_at'] ?? null],
            'signed' => (bool) $job['customer_signature'], 'signed_at' => $job['signed_at'],
            'invoice' => $prices && $invoice ? ['id' => (int) $invoice['id'], 'number' => $invoice['number'], 'balance' => (float) $invoice['balance_due'], 'status' => $invoice['status']] : null,
            'costing' => $prices ? JobCosting::for($id) : null,
            'tracker_url' => site_url('track/' . $job['public_token']),
            'pdf_url' => site_url('track/' . $job['public_token'] . '/job-card'),
            'can' => ['edit_prices' => $prices, 'invoice' => $prices && can('invoices') && !$invoice, 'ai' => \App\Libraries\AiAssistant::enabled(),
                'add_lines' => $this->lockedReason($id) === null, 'remove_lines' => $prices && can('invoices') && $this->lockedReason($id) === null],
        ]);
    }

    /** Quick check-in from the app. */
    public function store()
    {
        if ($this->isMechanic() || !can('job_cards')) {
            return $this->fail('Only the service desk can open job cards.', 403);
        }
        $b = $this->body();
        $cid = (int) $this->val($b, 'customer_id', 0);
        $vid = (int) $this->val($b, 'vehicle_id', 0);
        if (!$cid || !$vid || !$this->db->table('vehicles')->where(['id' => $vid, 'customer_id' => $cid])->countAllResults()) {
            return $this->fail('Choose the customer and one of their vehicles.');
        }
        $status = $this->val($b, 'status', 'checked_in') === 'booked' ? 'booked' : 'checked_in';
        $now = $this->now();
        $jobType = (string) $this->val($b, 'job_type', '');
        $this->db->transStart();
        $this->db->table('job_cards')->insert([
            'number' => Numbering::next('job_card'), 'branch_id' => session('branch_id'), 'customer_id' => $cid, 'vehicle_id' => $vid, 'status' => $status,
            'priority' => $this->val($b, 'priority', 'normal'), 'job_type' => $jobType ?: null, 'customer_complaint' => $this->val($b, 'customer_complaint'),
            'odometer_in' => (int) $this->val($b, 'odometer_in', 0) ?: null, 'fuel_level' => $this->val($b, 'fuel_level'),
            'booked_for' => $this->val($b, 'booked_for') ? date('Y-m-d H:i:s', strtotime($this->val($b, 'booked_for'))) : $now,
            'promised_at' => $this->val($b, 'promised_at') ? date('Y-m-d H:i:s', strtotime($this->val($b, 'promised_at'))) : null,
            'checked_in_at' => $status === 'checked_in' ? $now : null, 'public_token' => Billing::token(), 'advisor_id' => $this->uid(),
            'created_by' => $this->uid(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        JobWorkflow::seed($id, $jobType);
        foreach (array_unique(array_filter((array) ($b['mechanics'] ?? []))) as $m) {
            $this->db->table('job_card_mechanics')->insert(['job_card_id' => $id, 'user_id' => (int) $m, 'created_at' => $now]);
        }
        if ($pkg = (int) $this->val($b, 'canned_job_id', 0)) {
            LineItems::copy('canned_job', $pkg, 'job_card', $id);
        }
        if ($odo = (int) $this->val($b, 'odometer_in', 0)) {
            $this->db->table('vehicles')->where('id', $vid)->where('(odometer IS NULL OR odometer < ' . $odo . ')', null, false)->update(['odometer' => $odo]);
        }
        $this->db->transComplete();
        Activity::log('job_card', $id, 'created', 'Job card opened from the mobile app');
        foreach (array_unique(array_filter((array) ($b['mechanics'] ?? []))) as $m) {
            \App\Libraries\Notify::push([(int) $m], 'New job assigned', $this->db->table('vehicles')->select('registration')->where('id', $vid)->get()->getRow()->registration . ' · ' . ($this->val($b, 'customer_complaint') ?? ''), ['screen' => 'job', 'id' => $id]);
        }
        return $this->ok(['id' => $id], 201);
    }

    public function status(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $status = (string) $this->val($this->body(), 'status', '');
        if (!array_key_exists($status, job_statuses()) || ($this->isMechanic() && in_array($status, ['invoiced', 'collected', 'cancelled'], true))) {
            return $this->fail('You cannot set that status.');
        }
        $job = $this->db->table('job_cards')->where('id', $id)->get()->getRowArray();
        return $this->ok(['message' => JobWorkflow::changeStatus($job, $status, (int) $this->val($this->body(), 'odometer_out', 0))]);
    }

    public function notes(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $b = $this->body();
        $fields = ['customer_complaint', 'diagnosis', 'work_performed', 'recommendations'];
        if (!$this->isMechanic()) {
            $fields[] = 'internal_notes';
        }
        $data = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) {
                $data[$f] = mb_substr(trim((string) $b[$f]), 0, 5000) ?: null;
            }
        }
        if ($data) {
            $this->db->table('job_cards')->where('id', $id)->update($data + ['updated_at' => $this->now()]);
            Activity::log('job_card', $id, 'notes', 'Notes updated from the mobile app');
        }
        return $this->ok(['ok' => true]);
    }

    public function timer(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        [$running, $msg] = JobWorkflow::toggleTimer($id, $this->uid());
        return $this->ok(['running' => $running, 'message' => $msg]);
    }

    /** The technician's active timer anywhere (for the app's global "clocked on" banner). */
    public function activeTimer()
    {
        $t = $this->db->table('time_logs t')->select('t.job_card_id, t.started_at, j.number, v.registration')->join('job_cards j', 'j.id = t.job_card_id')->join('vehicles v', 'v.id = j.vehicle_id')
            ->where(['t.user_id' => $this->uid(), 't.ended_at' => null])->get()->getRowArray();
        return $this->ok(['timer' => $t ? ['job_id' => (int) $t['job_card_id'], 'number' => $t['number'], 'registration' => $t['registration'], 'started_at' => $t['started_at']] : null]);
    }

    public function checklist(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $b = $this->body();
        if ($itemId = (int) $this->val($b, 'item_id', 0)) {
            $done = !empty($b['done']);
            $this->db->table('job_checklist_items')->where(['id' => $itemId, 'job_card_id' => $id])->update(['is_done' => $done ? 1 : 0, 'done_by' => $done ? $this->uid() : null, 'done_at' => $done ? $this->now() : null, 'updated_at' => $this->now()]);
        } elseif ($item = $this->val($b, 'item')) {
            $this->db->table('job_checklist_items')->insert(['job_card_id' => $id, 'item' => mb_substr($item, 0, 255), 'sort_order' => 999, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        }
        return $this->ok(['ok' => true]);
    }

    public function inspection(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $b = $this->body();
        $data = ['updated_at' => $this->now()];
        if (array_key_exists('status', $b)) {
            $data['status'] = in_array($b['status'], ['ok', 'advisory', 'urgent', 'na'], true) ? $b['status'] : null;
        }
        if (array_key_exists('note', $b)) {
            $data['note'] = mb_substr(trim((string) $b['note']), 0, 500);
        }
        if (array_key_exists('estimate', $b) && !$this->isMechanic()) {
            $data['estimate'] = round(max(0, (float) $b['estimate']), 2);
        }
        $this->db->table('inspections')->where(['id' => (int) $this->val($b, 'item_id', 0), 'job_card_id' => $id])->update($data);
        return $this->ok(['ok' => true]);
    }

    public function damage(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $marks = $this->body()['marks'] ?? [];
        $marks = is_string($marks) ? (json_decode($marks, true) ?: []) : (array) $marks;
        $clean = [];
        foreach ($marks as $zone => $m) {
            if (preg_match('/^[a-z_]{2,30}$/', (string) $zone) && in_array($m['type'] ?? '', ['scratch', 'dent', 'chip', 'crack', 'rust', 'other'], true)) {
                $clean[$zone] = ['type' => $m['type'], 'note' => mb_substr((string) ($m['note'] ?? ''), 0, 120)];
            }
        }
        $this->db->table('job_cards')->where('id', $id)->update(['damage_marks' => json_encode($clean), 'updated_at' => $this->now()]);
        return $this->ok(['ok' => true, 'count' => count($clean)]);
    }

    /** Photo / video upload (multipart field "file", optional "category"). */
    public function upload(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $file = $this->request->getFile('file');
        if (!$file) {
            return $this->fail('No file received.');
        }
        try {
            $fid = Files::store($file, 'job_card', $id, (string) ($this->request->getPost('category') ?: 'photo'), $this->request->getPost('note'));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
        return $this->ok(['id' => $fid, 'url' => $this->fileUrl($fid)], 201);
    }

    public function signature(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $sig = (string) ($this->body()['signature'] ?? '');
        if (!str_starts_with($sig, 'data:image/png;base64,') || strlen($sig) > 500_000) {
            return $this->fail('Invalid signature.');
        }
        $this->db->table('job_cards')->where('id', $id)->update(['customer_signature' => $sig, 'signed_at' => $this->now()]);
        Activity::log('job_card', $id, 'signed', 'Customer signed on the mobile app');
        return $this->ok(['ok' => true]);
    }

    public function invoice(int $id)
    {
        if ($this->isMechanic() || !can('invoices')) {
            return $this->fail('Not allowed', 403);
        }
        if (!$this->db->table('line_items')->where(['document_type' => 'job_card', 'document_id' => $id])->countAllResults()) {
            return $this->fail('Add labour or parts before invoicing.');
        }
        return $this->ok(['invoice_id' => Billing::jobCardToInvoice($id)], 201);
    }

    /**
     * Add a line to a job: {product_id, quantity} for a stock item, {item_type:'labour', hours, description} for labour,
     * {canned_job_id} for a service package, or a free-text line with unit_price (managers only).
     * Technicians may add parts and labour at list price; they never see or set prices.
     */
    public function addItem(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        if ($r = $this->lockedReason($id)) {
            return $this->fail($r);
        }
        $b = $this->body();
        if ($pkg = (int) $this->val($b, 'canned_job_id', 0)) {
            $lines = $this->db->table('line_items')->where(['document_type' => 'canned_job', 'document_id' => $pkg])->orderBy('sort_order')->get()->getResultArray();
            if (!$lines) {
                return $this->fail('That package has no lines.');
            }
            foreach ($lines as $l) {
                $this->insertLine($id, $l);
            }
            Activity::log('job_card', $id, 'updated', 'Service package added from the app');
            return $this->ok(['totals' => LineItems::recalc('job_card', $id)], 201);
        }
        $manager = !$this->isMechanic() && can('invoices');
        $qty = max(0.01, (float) $this->val($b, 'quantity', 1));
        if ($pid = (int) $this->val($b, 'product_id', 0)) {
            $p = $this->db->table('products')->where(['id' => $pid, 'deleted_at' => null])->get()->getRowArray();
            if (!$p) {
                return $this->fail('Product not found.', 404);
            }
            $line = ['item_type' => in_array($p['type'], ['part', 'fluid', 'tyre', 'service', 'labour', 'sundry'], true) ? $p['type'] : 'part', 'product_id' => $pid, 'description' => $p['name'],
                'quantity' => $qty, 'unit_cost' => $p['cost_price'], 'unit_price' => $manager && $this->val($b, 'unit_price') !== null ? $b['unit_price'] : $p['sell_price'],
                'vat_rate' => $p['vat_rate'], 'warranty_months' => $p['warranty_months']];
        } elseif ($this->val($b, 'item_type') === 'labour') {
            $hours = max(0.1, (float) $this->val($b, 'hours', $qty));
            $line = ['item_type' => 'labour', 'description' => (string) $this->val($b, 'description', 'Labour'), 'quantity' => $hours,
                'unit_price' => $manager && $this->val($b, 'unit_price') !== null ? $b['unit_price'] : setting('default_labour_rate', 650),
                'mechanic_id' => $this->isMechanic() ? $this->uid() : ((int) $this->val($b, 'mechanic_id', 0) ?: null)];
        } else {
            if (!$manager) {
                return $this->fail('Pick a part from stock or add labour.', 403);
            }
            if (!$this->val($b, 'description')) {
                return $this->fail('Enter a description.');
            }
            $line = ['item_type' => (string) $this->val($b, 'item_type', 'part'), 'description' => (string) $b['description'], 'quantity' => $qty,
                'unit_price' => (float) $this->val($b, 'unit_price', 0), 'unit_cost' => (float) $this->val($b, 'unit_cost', 0), 'discount_percent' => (float) $this->val($b, 'discount_percent', 0)];
        }
        $this->insertLine($id, $line);
        Activity::log('job_card', $id, 'updated', 'Added ' . $line['description'] . ' x' . $line['quantity'] . ' from the app');
        return $this->ok(['totals' => LineItems::recalc('job_card', $id)], 201);
    }

    public function removeItem(int $id, int $itemId)
    {
        if ($this->isMechanic() || !can('invoices')) {
            return $this->fail('Ask the service advisor to remove lines.', 403);
        }
        if ($r = $this->lockedReason($id)) {
            return $this->fail($r);
        }
        $this->db->table('line_items')->where(['id' => $itemId, 'document_type' => 'job_card', 'document_id' => $id])->delete();
        Activity::log('job_card', $id, 'updated', 'Removed a line from the app');
        return $this->ok(['totals' => LineItems::recalc('job_card', $id)]);
    }

    private function lockedReason(int $id): ?string
    {
        $job = $this->db->table('job_cards')->select('status')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (!$job) {
            return 'Job not found.';
        }
        if (in_array($job['status'], ['invoiced', 'collected', 'cancelled'], true)
            || $this->db->table('invoices')->where(['job_card_id' => $id, 'deleted_at' => null])->where('status !=', 'void')->countAllResults()) {
            return 'This job has been invoiced, so its lines are locked.';
        }
        return null;
    }

    private function insertLine(int $jobId, array $line): void
    {
        $next = (int) ($this->db->table('line_items')->selectMax('sort_order', 'm')->where(['document_type' => 'job_card', 'document_id' => $jobId])->get()->getRow()->m ?? -1) + 1;
        $this->db->table('line_items')->insert(LineItems::calc($line) + ['document_type' => 'job_card', 'document_id' => $jobId, 'sort_order' => $next, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
    }

    public function aiSummary(int $id)
    {
        if (!$this->canSeeJob($id)) {
            return $this->fail('Not allowed', 403);
        }
        $d = DocData::jobCard($id);
        try {
            $text = \App\Libraries\AiAssistant::customerSummary(\App\Libraries\AiAssistant::jobContext($d['job'], $d['vehicle'], $d['inspections']), $d['items']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 503);
        }
        return $this->ok(['text' => $text]);
    }
}
