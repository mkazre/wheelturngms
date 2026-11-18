<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Billing;
use App\Libraries\Mailer;
use App\Libraries\Notify;
use App\Libraries\Numbering;

/** Booking diary (calendar) and online booking requests. */
class Diary extends BaseController
{
    public function index()
    {
        $pending = $this->db->table('bookings')->where('status', 'pending')->orderBy('preferred_at')->get()->getResultArray();
        return view('diary/index', ['pending' => $pending, 'bays' => (int) setting('workshop_bays', 4)]);
    }

    /** FullCalendar JSON feed. */
    public function events()
    {
        $start = substr((string) $this->request->getGet('start'), 0, 10) ?: date('Y-m-01');
        $end = substr((string) $this->request->getGet('end'), 0, 10) ?: date('Y-m-t');
        $statuses = job_statuses();
        $events = [];
        $jobs = $this->db->table('job_cards j')->select('j.id, j.number, j.status, j.booked_for, j.promised_at, j.job_type, j.bay, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->where('j.deleted_at', null)->where('j.status !=', 'cancelled')->where('j.booked_for >=', $start)->where('j.booked_for <', $end . ' 23:59:59')->get()->getResultArray();
        foreach ($jobs as $j) {
            $endAt = $j['promised_at'] && $j['promised_at'] > $j['booked_for'] ? $j['promised_at'] : date('Y-m-d H:i:s', strtotime($j['booked_for'] . ' +2 hours'));
            $events[] = [
                'id' => 'job-' . $j['id'], 'title' => $j['registration'] . ' · ' . trim($j['make'] . ' ' . $j['model']) . ' · ' . customer_name($j),
                'start' => date('c', strtotime($j['booked_for'])), 'end' => date('c', strtotime($endAt)),
                'url' => site_url('job-cards/' . $j['id']), 'backgroundColor' => $statuses[$j['status']]['hex'] ?? '#64748b', 'borderColor' => 'transparent',
                'extendedProps' => ['kind' => 'job', 'status' => $statuses[$j['status']]['label'] ?? $j['status'], 'type' => $j['job_type'], 'bay' => $j['bay'], 'number' => $j['number']],
            ];
        }
        $bookings = $this->db->table('bookings')->whereIn('status', ['pending', 'confirmed'])->where('preferred_at >=', $start)->where('preferred_at <', $end . ' 23:59:59')->get()->getResultArray();
        foreach ($bookings as $b) {
            $events[] = [
                'id' => 'booking-' . $b['id'], 'title' => ($b['status'] === 'pending' ? 'REQUEST: ' : '') . $b['name'] . ' · ' . ($b['service'] ?: 'Booking') . ($b['registration'] ? ' · ' . $b['registration'] : ''),
                'start' => date('c', strtotime($b['preferred_at'])), 'end' => date('c', strtotime($b['preferred_at'] . ' +1 hour')),
                'backgroundColor' => $b['status'] === 'pending' ? '#fbbf24' : '#1c205c', 'borderColor' => 'transparent', 'textColor' => $b['status'] === 'pending' ? '#0b0d2e' : '#fff',
                'extendedProps' => ['kind' => 'booking', 'status' => ucfirst($b['status']), 'bookingId' => $b['id']],
            ];
        }
        return $this->json($events);
    }

    /** Drag & drop reschedule. */
    public function move()
    {
        [$kind, $id] = array_pad(explode('-', (string) $this->request->getPost('id'), 2), 2, 0);
        $start = date('Y-m-d H:i:s', strtotime((string) $this->request->getPost('start')));
        if ($kind === 'job') {
            $job = $this->db->table('job_cards')->select('booked_for, promised_at')->where('id', (int) $id)->get()->getRowArray();
            $shift = strtotime($start) - strtotime((string) $job['booked_for']);
            $this->db->table('job_cards')->where('id', (int) $id)->update(['booked_for' => $start, 'promised_at' => $job['promised_at'] ? date('Y-m-d H:i:s', strtotime($job['promised_at']) + $shift) : null, 'updated_at' => $this->now()]);
            Activity::log('job_card', (int) $id, 'rescheduled', 'Moved to ' . fdatetime($start));
        } elseif ($kind === 'booking') {
            $this->db->table('bookings')->where('id', (int) $id)->update(['preferred_at' => $start, 'updated_at' => $this->now()]);
        }
        return $this->json(['ok' => true, 'message' => 'Rescheduled to ' . fdatetime($start)]);
    }

    /** Confirm a booking request: match/create customer + vehicle, then create a booked job card. */
    public function confirm(int $id)
    {
        $jobId = \App\Libraries\Bookings::confirm($id);
        if (!$jobId) {
            return redirect()->back()->with('error', 'Booking not found.');
        }
        return redirect()->to(site_url('job-cards/' . $jobId))->with('success', 'Booking confirmed and job card created. Complete the vehicle details at check-in.');
    }

    public function decline(int $id)
    {
        $b = $this->db->table('bookings')->where('id', $id)->get()->getRowArray();
        $this->db->table('bookings')->where('id', $id)->update(['status' => 'declined', 'updated_at' => $this->now()]);
        if ($b && $b['email'] && $this->request->getPost('notify')) {
            Mailer::send($b['email'], 'Your booking request', '<p>Hi ' . esc($b['name']) . ',</p><p>Unfortunately we cannot accommodate your requested time (' . fdatetime($b['preferred_at']) . '). ' . esc((string) $this->request->getPost('message')) . '</p><p>Please call us on ' . esc(setting('company_phone')) . ' or book another slot online.</p>', [], 'booking', $id);
        }
        return redirect()->back()->with('success', 'Booking declined.');
    }

    // ------------------------------------------------------------ public online booking

    public function bookForm()
    {
        $services = array_merge(array_column($this->db->table('canned_jobs')->select('name')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(), 'name'), \App\Controllers\JobCards::JOB_TYPES);
        return view('public/book', ['services' => array_values(array_unique($services)), 'done' => $this->request->getGet('done')]);
    }

    public function bookStore()
    {
        if ($this->request->getPost('website')) { // honeypot
            return redirect()->to(site_url('book?done=1'));
        }
        if (service('throttler')->check(md5('book' . $this->request->getIPAddress()), 5, 3600) === false) {
            return redirect()->back()->withInput()->with('error', 'Too many booking requests. Please call us instead.');
        }
        if (!$this->validate(['name' => 'required|max_length[150]', 'phone' => 'required|max_length[50]', 'email' => 'permit_empty|valid_email', 'date' => 'required|valid_date[Y-m-d]', 'time' => 'required|regex_match[/^\d{2}:\d{2}$/]', 'popia' => 'required'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $at = $this->input('date') . ' ' . $this->input('time') . ':00';
        if (strtotime($at) < time()) {
            return redirect()->back()->withInput()->with('error', 'Please choose a time in the future.');
        }
        if ((int) date('N', strtotime($at)) === 7) {
            return redirect()->back()->withInput()->with('error', 'We are closed on Sundays. Please choose another day.');
        }
        $this->db->table('bookings')->insert([
            'name' => $this->input('name'), 'phone' => $this->input('phone'), 'email' => $this->input('email') ? strtolower($this->input('email')) : null,
            'registration' => strtoupper((string) $this->input('registration')), 'vehicle_desc' => $this->input('vehicle'), 'service' => $this->input('service'),
            'preferred_at' => $at, 'notes' => $this->input('notes'), 'status' => 'pending', 'source' => 'online', 'token' => bin2hex(random_bytes(16)),
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        $id = (int) $this->db->insertID();
        Notify::staff('New online booking: ' . $this->input('name'), ($this->input('service') ?: 'Service') . ' · ' . fdatetime($at), 'diary', 'calendar');
        Mailer::send(setting('company_email'), 'New online booking request: ' . $this->input('name'), '<p><b>' . esc($this->input('name')) . '</b> (' . esc($this->input('phone')) . ') requested <b>' . esc($this->input('service') ?: 'a booking') . '</b> on <b>' . fdatetime($at) . '</b>.</p>' . view('emails/_pay_button', ['url' => site_url('diary'), 'label' => 'Open booking diary']), [], 'booking', $id);
        if ($this->input('email')) {
            Mailer::send($this->input('email'), 'We received your booking request', '<p>Hi ' . esc($this->input('name')) . ',</p><p>Thanks for booking with <b>' . esc(setting('company_name')) . '</b>. We have received your request for <b>' . fdatetime($at) . '</b> and will confirm shortly.</p>', [], 'booking', $id);
        }
        return redirect()->to(site_url('book?done=1'));
    }
}
