<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Pdf;

class JobCards extends BaseController
{
    public const JOB_TYPES = ['Service', 'Repair', 'Diagnostics', 'Brakes', 'Suspension', 'Clutch & gearbox', 'Electrical', 'Air conditioning', 'Tyres & alignment', 'Roadworthy', 'Panel & paint', 'Warranty', 'Insurance claim', 'Other'];
    public const FUEL = ['E', '1/4', '1/2', '3/4', 'F'];

    private function isMechanic(): bool
    {
        return session('role') === 'mechanic';
    }

    private function mechanics(): array
    {
        return $this->db->table('users')->select('id, first_name, last_name, hourly_rate')->whereIn('role', ['mechanic', 'manager'])
            ->where(['is_active' => 1, 'deleted_at' => null])->orderBy('first_name')->get()->getResultArray();
    }

    private function query()
    {
        // GROUP_CONCAT separator syntax differs between MySQL and SQLite
        $gc = $this->db->DBDriver === 'MySQLi' ? "GROUP_CONCAT(u.first_name SEPARATOR ', ')" : "GROUP_CONCAT(u.first_name, ', ')";
        $b = $this->db->table('job_cards j')
            ->select('j.*, v.registration, v.make, v.model, v.year, c.first_name, c.last_name, c.company_name, c.type')
            ->select("(SELECT {$gc} FROM job_card_mechanics jm JOIN users u ON u.id = jm.user_id WHERE jm.job_card_id = j.id) mechanic_names", false)
            ->join('vehicles v', 'v.id = j.vehicle_id', 'left')->join('customers c', 'c.id = j.customer_id', 'left')
            ->where('j.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('j.number', $q)->orLike('v.registration', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->orLike('j.customer_complaint', $q)->groupEnd();
        }
        $status = $this->request->getGet('status');
        if ($status === 'open' || ($status === null && $this->isMechanic())) {
            $b->whereNotIn('j.status', ['invoiced', 'collected', 'cancelled']);
        } elseif ($status && $status !== 'all') {
            $b->where('j.status', $status);
        }
        if ($this->isMechanic() || $this->request->getGet('mine')) {
            $b->where('j.id IN (SELECT job_card_id FROM job_card_mechanics WHERE user_id = ' . (int) session('user_id') . ')', null, false);
        }
        return $b->orderBy('j.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $counts = array_column($this->db->table('job_cards')->select('status, COUNT(*) n')->where('deleted_at', null)->groupBy('status')->get()->getResultArray(), 'n', 'status');
        return view('job_cards/index', compact('rows', 'pager', 'counts'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'vehicle' => trim($r['make'] . ' ' . $r['model']), 'status_label' => job_statuses()[$r['status']]['label'] ?? $r['status']], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Job Cards', [
            'number' => ['label' => 'Job no.'], 'created_at' => ['label' => 'Opened'], 'customer' => ['label' => 'Customer'], 'registration' => ['label' => 'Reg.'],
            'vehicle' => ['label' => 'Vehicle'], 'job_type' => ['label' => 'Type'], 'status_label' => ['label' => 'Status'], 'mechanic_names' => ['label' => 'Technicians'],
            'total' => ['label' => 'Total (incl.)', 'type' => 'money'],
        ], $rows, [], ['number' => 'Total', 'total' => array_sum(array_column($rows, 'total'))]);
    }

    public function form(?int $id = null)
    {
        if ($id) {
            $data = DocData::jobCard($id);
            if (!$data) {
                return $this->notFound('Job card');
            }
            $job = $data['job'];
            $lines = $data['items'];
            $assigned = array_column($data['mechanics'], 'id');
            $customer = $data['customer'];
            $vehicle = $data['vehicle'];
        } else {
            $bookedFor = (string) $this->request->getGet('booked_for');
            $future = $bookedFor && strtotime($bookedFor) > time() + 3600;
            $job = ['status' => $future ? 'booked' : 'checked_in', 'priority' => 'normal', 'booked_for' => $bookedFor ? date('Y-m-d H:i', strtotime($bookedFor)) : date('Y-m-d H:i'), 'fuel_level' => '1/2'];
            $lines = [];
            $assigned = [];
            $customer = ($cid = (int) $this->request->getGet('customer_id')) ? $this->db->table('customers')->where('id', $cid)->get()->getRowArray() : null;
            $vehicle = ($vid = (int) $this->request->getGet('vehicle_id')) ? $this->db->table('vehicles')->where('id', $vid)->get()->getRowArray() : null;
        }
        $advisors = $this->db->table('users')->select('id, first_name, last_name')->whereIn('role', ['admin', 'manager', 'advisor'])->where(['is_active' => 1, 'deleted_at' => null])->get()->getResultArray();
        return view('job_cards/form', [
            'job' => $job, 'lines' => $lines, 'assigned' => $assigned, 'customer' => $customer, 'vehicle' => $vehicle,
            'mechanics' => $this->mechanics(), 'advisors' => $advisors,
        ]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['customer_id' => 'required|is_natural_no_zero', 'vehicle_id' => 'required|is_natural_no_zero', 'odometer_in' => 'permit_empty|integer'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['customer_id', 'vehicle_id', 'priority', 'job_type', 'advisor_id', 'bay', 'odometer_in', 'fuel_level', 'customer_complaint', 'diagnosis', 'work_performed', 'recommendations', 'internal_notes', 'booked_for', 'promised_at']);
        foreach (['booked_for', 'promised_at'] as $f) {
            $data[$f] = $data[$f] ? date('Y-m-d H:i:s', strtotime($data[$f])) : null;
        }
        $data['updated_at'] = $this->now();

        $this->db->transStart();
        if ($id) {
            $this->db->table('job_cards')->where('id', $id)->update($data);
            Activity::log('job_card', $id, 'updated', 'Job card updated');
        } else {
            $status = $this->input('status', 'checked_in');
            $data += [
                'number' => Numbering::next('job_card'), 'branch_id' => session('branch_id'), 'status' => in_array($status, ['booked', 'checked_in'], true) ? $status : 'checked_in',
                'public_token' => Billing::token(), 'created_by' => session('user_id'), 'created_at' => $this->now(),
                'checked_in_at' => $status === 'checked_in' ? $this->now() : null,
            ];
            $data['advisor_id'] ??= session('user_id');
            $this->db->table('job_cards')->insert($data);
            $id = (int) $this->db->insertID();
            $this->seedInspection($id);
            $this->seedChecklist($id, (string) $data['job_type']);
            Activity::log('job_card', $id, 'created', 'Job card ' . $data['number'] . ' opened');
            \App\Libraries\Notify::staff('New job card ' . $data['number'], 'Vehicle checked in', 'job-cards/' . $id, 'wrench', null, 'manager');
        }
        LineItems::sync('job_card', $id, (array) $this->request->getPost('items'));

        $this->db->table('job_card_mechanics')->where('job_card_id', $id)->delete();
        foreach (array_unique(array_filter((array) $this->request->getPost('mechanics'))) as $uid) {
            $this->db->table('job_card_mechanics')->insert(['job_card_id' => $id, 'user_id' => (int) $uid, 'created_at' => $this->now()]);
        }
        if ($data['odometer_in']) {
            $this->db->table('vehicles')->where('id', $data['vehicle_id'])->where('(odometer IS NULL OR odometer < ' . (int) $data['odometer_in'] . ')', null, false)->update(['odometer' => (int) $data['odometer_in']]);
        }
        $this->db->transComplete();
        return redirect()->to(site_url('job-cards/' . $id))->with('success', 'Job card saved.');
    }

    private function seedInspection(int $jobId): void
    {
        $i = 0;
        foreach (preg_split('/\r?\n/', (string) setting('inspection_template', '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$section, $item] = array_pad(explode('|', $line, 2), 2, null);
            if ($item === null) {
                [$section, $item] = ['General', $section];
            }
            $this->db->table('inspections')->insert(['job_card_id' => $jobId, 'section' => trim($section), 'item' => trim($item), 'status' => null, 'sort_order' => $i++, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        }
    }

    /** Attach technician checklist steps for this job type (plus "all jobs" templates). */
    private function seedChecklist(int $jobId, string $jobType): void
    {
        $tpls = $this->db->table('checklist_templates')->where('is_active', 1)
            ->groupStart()->where('job_type', null)->orWhere('job_type', '')->orWhere('job_type', $jobType)->groupEnd()->get()->getResultArray();
        $i = 0;
        foreach ($tpls as $t) {
            foreach (preg_split('/\r?\n/', (string) $t['items']) as $step) {
                if (($step = trim($step)) !== '') {
                    $this->db->table('job_checklist_items')->insert(['job_card_id' => $jobId, 'item' => mb_substr($step, 0, 255), 'sort_order' => $i++, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
                }
            }
        }
    }

    /** AJAX: tick / untick a checklist step, or add a custom one. */
    public function checklist(int $id)
    {
        if ($itemId = (int) $this->request->getPost('item_id')) {
            $done = (int) $this->request->getPost('done') === 1;
            $this->db->table('job_checklist_items')->where(['id' => $itemId, 'job_card_id' => $id])->update([
                'is_done' => $done ? 1 : 0, 'done_by' => $done ? session('user_id') : null, 'done_at' => $done ? $this->now() : null, 'updated_at' => $this->now(),
            ]);
            return $this->json(['ok' => true]);
        }
        if ($item = trim((string) $this->request->getPost('item'))) {
            $this->db->table('job_checklist_items')->insert(['job_card_id' => $id, 'item' => mb_substr($item, 0, 255), 'sort_order' => 999, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        }
        return redirect()->to(site_url('job-cards/' . $id . '?tab=checklist'));
    }

    /** AJAX: save vehicle damage diagram marks (JSON: {zone: {type, note}}). */
    public function damage(int $id)
    {
        $marks = json_decode((string) $this->request->getPost('marks'), true);
        if (!is_array($marks)) {
            return $this->json(['error' => 'Invalid data'], 422);
        }
        $clean = [];
        foreach ($marks as $zone => $m) {
            if (preg_match('/^[a-z_]{2,30}$/', (string) $zone) && in_array($m['type'] ?? '', ['scratch', 'dent', 'chip', 'crack', 'rust', 'other'], true)) {
                $clean[$zone] = ['type' => $m['type'], 'note' => mb_substr((string) ($m['note'] ?? ''), 0, 120)];
            }
        }
        $this->db->table('job_cards')->where('id', $id)->update(['damage_marks' => json_encode($clean), 'updated_at' => $this->now()]);
        return $this->json(['ok' => true, 'count' => count($clean)]);
    }

    public function show(int $id)
    {
        $data = DocData::jobCard($id);
        if (!$data) {
            return $this->notFound('Job card');
        }
        if ($this->isMechanic() && !in_array((int) session('user_id'), array_map('intval', array_column($data['mechanics'], 'id')), true)) {
            return redirect()->to(site_url('job-cards'))->with('error', 'This job is not assigned to you.');
        }
        $timeLogs = $this->db->table('time_logs t')->select('t.*, u.first_name, u.last_name')->join('users u', 'u.id = t.user_id')
            ->where('t.job_card_id', $id)->orderBy('t.id', 'DESC')->get()->getResultArray();
        $running = $this->db->table('time_logs')->where(['job_card_id' => $id, 'user_id' => session('user_id'), 'ended_at' => null])->get()->getRowArray();
        $invoice = $this->db->table('invoices')->where(['job_card_id' => $id, 'deleted_at' => null])->where('status !=', 'void')->get()->getRowArray();
        $quote = $data['job']['quote_id'] ? $this->db->table('quotes')->select('id, number, status')->where('id', $data['job']['quote_id'])->get()->getRowArray() : null;

        return view('job_cards/show', $data + [
            'timeLogs' => $timeLogs, 'running' => $running, 'invoice' => $invoice, 'quote' => $quote,
            'files' => Files::for('job_card', $id), 'activity' => Activity::for('job_card', $id),
            'showPrices' => !$this->isMechanic(),
            'checklist' => $this->db->table('job_checklist_items c')->select('c.*, u.first_name done_by_name')->join('users u', 'u.id = c.done_by', 'left')->where('c.job_card_id', $id)->orderBy('c.sort_order')->orderBy('c.id')->get()->getResultArray(),
            'costing' => \App\Libraries\JobCosting::for($id),
            'loanCars' => $this->db->table('loan_vehicles')->where(['is_active' => 1, 'status' => 'available'])->get()->getResultArray(),
            'loan' => $this->db->table('loan_bookings b')->select('b.*, l.registration, l.description')->join('loan_vehicles l', 'l.id = b.loan_vehicle_id')->where(['b.job_card_id' => $id, 'b.returned_at' => null])->get()->getRowArray(),
            'deposits' => $this->db->table('customer_deposits')->where('job_card_id', $id)->where('status !=', 'refunded')->get()->getResultArray(),
        ]);
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (!array_key_exists($status, job_statuses())) {
            return $this->respondStatus(false, 'Invalid status.');
        }
        $job = $this->db->table('job_cards')->where('id', $id)->get()->getRowArray();
        if (!$job) {
            return $this->notFound('Job card');
        }
        if ($this->isMechanic() && in_array($status, ['invoiced', 'collected', 'cancelled'], true)) {
            return $this->respondStatus(false, 'Only the service desk can make that change.');
        }
        $update = ['status' => $status, 'updated_at' => $this->now()];
        $stamp = ['checked_in' => 'checked_in_at', 'in_progress' => 'started_at', 'completed' => 'completed_at', 'collected' => 'collected_at'];
        if (isset($stamp[$status]) && empty($job[$stamp[$status]])) {
            $update[$stamp[$status]] = $this->now();
        }
        if ($status === 'collected' && ($odo = (int) $this->request->getPost('odometer_out'))) {
            $update['odometer_out'] = $odo;
        }
        $this->db->table('job_cards')->where('id', $id)->update($update);
        $label = job_statuses()[$status]['label'];
        Activity::log('job_card', $id, 'status', 'Status changed to ' . $label);

        $notified = false;
        if ($status === 'completed' && (int) setting('notify_vehicle_ready', 1) === 1 && $job['status'] !== 'completed') {
            $notified = Billing::sendVehicleReady($id);
        }
        return $this->respondStatus(true, 'Status updated to ' . $label . ($notified ? ' · customer notified' : ''));
    }

    private function respondStatus(bool $ok, string $msg)
    {
        if ($this->request->isAJAX()) {
            return $this->json(['ok' => $ok, 'message' => $msg], $ok ? 200 : 422);
        }
        return redirect()->back()->with($ok ? 'success' : 'error', $msg);
    }

    /** AJAX: update one inspection item (status / note) or add a custom item. */
    public function inspection(int $id)
    {
        $itemId = (int) $this->request->getPost('item_id');
        if ($itemId) {
            $data = ['updated_at' => $this->now()];
            if (($s = $this->request->getPost('status')) !== null) {
                $data['status'] = in_array($s, ['ok', 'advisory', 'urgent', 'na'], true) ? $s : null;
            }
            if (($n = $this->request->getPost('note')) !== null) {
                $data['note'] = mb_substr(trim((string) $n), 0, 500);
            }
            if (($est = $this->request->getPost('estimate')) !== null) {
                $data['estimate'] = round(max(0, (float) $est), 2);
            }
            $this->db->table('inspections')->where(['id' => $itemId, 'job_card_id' => $id])->update($data);
            return $this->json(['ok' => true]);
        }
        $item = trim((string) $this->request->getPost('item'));
        if ($item !== '') {
            $this->db->table('inspections')->insert(['job_card_id' => $id, 'section' => $this->input('section', 'Additional'), 'item' => $item, 'sort_order' => 999, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            return redirect()->back()->with('success', 'Inspection item added.');
        }
        return redirect()->back();
    }

    /** Clock on / clock off the current technician. */
    public function timer(int $id)
    {
        $uid = (int) session('user_id');
        $running = $this->db->table('time_logs')->where(['user_id' => $uid, 'ended_at' => null])->get()->getRowArray();
        if ($running) {
            $mins = max(1, (int) round((time() - strtotime($running['started_at'])) / 60));
            $this->db->table('time_logs')->where('id', $running['id'])->update(['ended_at' => $this->now(), 'minutes' => $mins, 'updated_at' => $this->now()]);
            Activity::log('job_card', (int) $running['job_card_id'], 'clock_off', 'Clocked off after ' . $mins . ' min');
            if ((int) $running['job_card_id'] === $id) {
                return redirect()->back()->with('success', 'Clocked off · ' . $mins . ' minutes logged.');
            }
        }
        $this->db->table('time_logs')->insert(['job_card_id' => $id, 'user_id' => $uid, 'started_at' => $this->now(), 'note' => $this->input('note'), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        $job = $this->db->table('job_cards')->select('status, started_at')->where('id', $id)->get()->getRowArray();
        if (in_array($job['status'], ['booked', 'checked_in'], true)) {
            $this->db->table('job_cards')->where('id', $id)->update(['status' => 'in_progress', 'started_at' => $job['started_at'] ?: $this->now()]);
        }
        Activity::log('job_card', $id, 'clock_on', 'Clocked on');
        return redirect()->back()->with('success', 'Clocked on. Timer running.');
    }

    public function signature(int $id)
    {
        $sig = (string) $this->request->getPost('signature');
        if (!str_starts_with($sig, 'data:image/png;base64,') || strlen($sig) > 500_000) {
            return redirect()->back()->with('error', 'Invalid signature.');
        }
        $this->db->table('job_cards')->where('id', $id)->update(['customer_signature' => $sig, 'signed_at' => $this->now()]);
        Activity::log('job_card', $id, 'signed', 'Customer signed the job card');
        return redirect()->back()->with('success', 'Signature captured.');
    }

    public function invoice(int $id)
    {
        if ($this->isMechanic() || !can('invoices')) {
            return redirect()->back()->with('error', 'You cannot create invoices.');
        }
        $items = $this->db->table('line_items')->where(['document_type' => 'job_card', 'document_id' => $id])->countAllResults();
        if (!$items) {
            return redirect()->back()->with('error', 'Add labour or parts before invoicing.');
        }
        $invId = Billing::jobCardToInvoice($id);
        return redirect()->to(site_url('invoices/' . $invId))->with('success', 'Tax invoice created. Stock and ledger updated.');
    }

    public function pdf(int $id)
    {
        $data = DocData::jobCard($id);
        if (!$data) {
            return $this->notFound('Job card');
        }
        $data['showPrices'] = !$this->isMechanic() && $this->request->getGet('prices') !== '0';
        return Pdf::respond(Pdf::render('pdf/job_card', $data), $data['job']['number'] . '.pdf');
    }

    /** Traffic-light vehicle health report from the inspection. ?email=1 sends it to the customer. */
    public function healthReport(int $id)
    {
        $data = DocData::jobCard($id) ?? $this->notFound('Job card');
        $pdf = Pdf::render('pdf/health_report', $data);
        if ($this->request->getGet('email')) {
            $c = $data['customer'];
            $ok = $c['email'] && \App\Libraries\Mailer::send($c['email'], 'Vehicle health report: ' . $data['vehicle']['registration'],
                '<p>Hi ' . esc(customer_name($c)) . ',</p><p>Our technician has inspected your <b>' . esc(trim($data['vehicle']['make'] . ' ' . $data['vehicle']['model'])) . '</b>. Your health report is attached. You can approve any recommended work online.</p>'
                . view('emails/_pay_button', ['url' => site_url('track/' . $data['job']['public_token']), 'label' => 'View findings & approve online']),
                [['content' => $pdf, 'name' => 'Health-Report-' . $data['job']['number'] . '.pdf']], 'job_card', $id);
            Activity::log('job_card', $id, 'health_report', 'Health report ' . ($ok ? 'emailed' : 'email failed'));
            return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Health report emailed to the customer.' : 'Could not email the report. Check the customer email and SMTP.');
        }
        return Pdf::respond($pdf, 'Health-Report-' . $data['job']['number'] . '.pdf');
    }

    public function delete(int $id)
    {
        if ($this->db->table('invoices')->where(['job_card_id' => $id, 'deleted_at' => null])->where('status !=', 'void')->countAllResults()) {
            return redirect()->back()->with('error', 'This job card has an invoice. Void the invoice first.');
        }
        $this->db->table('job_cards')->where('id', $id)->update(['deleted_at' => $this->now(), 'status' => 'cancelled']);
        Activity::log('job_card', $id, 'deleted', 'Job card cancelled');
        return redirect()->to(site_url('job-cards'))->with('success', 'Job card cancelled.');
    }
}
