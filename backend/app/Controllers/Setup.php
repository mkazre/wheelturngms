<?php

namespace App\Controllers;

use App\Libraries\Activity;

/** Workshop setup: technician checklists and courtesy (loan) vehicles. */
class Setup extends BaseController
{
    // ------------------------------------------------------------ checklists

    public function checklists()
    {
        return view('setup/checklists', ['rows' => $this->db->table('checklist_templates')->orderBy('name')->get()->getResultArray()]);
    }

    public function saveChecklist()
    {
        $id = (int) $this->request->getPost('id');
        $data = $this->only(['name', 'job_type', 'items']) + ['is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now()];
        if (!$data['name'] || !trim((string) $data['items'])) {
            return redirect()->back()->with('error', 'Name and at least one checklist item are required.');
        }
        if ($this->request->getPost('delete') && $id) {
            $this->db->table('checklist_templates')->where('id', $id)->delete();
            return redirect()->back()->with('success', 'Checklist deleted.');
        }
        $id ? $this->db->table('checklist_templates')->where('id', $id)->update($data) : $this->db->table('checklist_templates')->insert($data + ['created_at' => $this->now()]);
        return redirect()->back()->with('success', 'Checklist saved.');
    }

    // ------------------------------------------------------------ loan vehicles

    public function loans()
    {
        $vehicles = $this->db->table('loan_vehicles')->where('is_active', 1)->orderBy('registration')->get()->getResultArray();
        $out = $this->db->table('loan_bookings b')->select('b.*, l.registration, l.description, c.first_name, c.last_name, c.company_name, c.type, c.phone, j.number job_number')
            ->join('loan_vehicles l', 'l.id = b.loan_vehicle_id')->join('customers c', 'c.id = b.customer_id')->join('job_cards j', 'j.id = b.job_card_id', 'left')
            ->orderBy('b.returned_at IS NULL', 'DESC', false)->orderBy('b.out_at', 'DESC')->limit(50)->get()->getResultArray();
        return view('setup/loans', ['vehicles' => $vehicles, 'bookings' => $out]);
    }

    public function saveLoanVehicle()
    {
        $id = (int) $this->request->getPost('id');
        $data = $this->only(['registration', 'description', 'notes']) + ['odometer' => (int) $this->input('odometer', 0) ?: null, 'updated_at' => $this->now()];
        if (!$data['registration']) {
            return redirect()->back()->with('error', 'Registration is required.');
        }
        $data['registration'] = strtoupper($data['registration']);
        if ($id) {
            $this->db->table('loan_vehicles')->where('id', $id)->update($data + ['is_active' => $this->request->getPost('retire') ? 0 : 1]);
        } else {
            $this->db->table('loan_vehicles')->insert($data + ['status' => 'available', 'is_active' => 1, 'created_at' => $this->now()]);
        }
        return redirect()->back()->with('success', 'Loan vehicle saved.');
    }

    /** Book a loan car out to a customer (usually from a job card). */
    public function loanOut()
    {
        $vid = (int) $this->request->getPost('loan_vehicle_id');
        $car = $this->db->table('loan_vehicles')->where(['id' => $vid, 'status' => 'available'])->get()->getRowArray();
        if (!$car) {
            return redirect()->back()->with('error', 'That loan vehicle is not available.');
        }
        $jobId = (int) $this->request->getPost('job_card_id') ?: null;
        $custId = (int) $this->request->getPost('customer_id');
        if (!$custId && $jobId) {
            $custId = (int) $this->db->table('job_cards')->select('customer_id')->where('id', $jobId)->get()->getRow()->customer_id;
        }
        $this->db->table('loan_bookings')->insert([
            'loan_vehicle_id' => $vid, 'customer_id' => $custId, 'job_card_id' => $jobId, 'out_at' => $this->now(),
            'due_at' => $this->input('due_at') ? date('Y-m-d H:i:s', strtotime($this->input('due_at'))) : null,
            'odometer_out' => (int) $this->input('odometer_out', $car['odometer']) ?: null, 'fuel_out' => $this->input('fuel_out'),
            'licence_number' => $this->input('licence_number'), 'notes' => $this->input('notes'), 'created_by' => session('user_id'),
            'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        $this->db->table('loan_vehicles')->where('id', $vid)->update(['status' => 'out']);
        if ($jobId) {
            $this->db->table('job_cards')->where('id', $jobId)->update(['loan_vehicle_id' => $vid]);
            Activity::log('job_card', $jobId, 'loan_out', 'Loan vehicle ' . $car['registration'] . ' issued');
        }
        return redirect()->back()->with('success', 'Loan vehicle ' . $car['registration'] . ' booked out.');
    }

    public function loanReturn(int $bookingId)
    {
        $b = $this->db->table('loan_bookings')->where('id', $bookingId)->get()->getRowArray();
        if (!$b || $b['returned_at']) {
            return redirect()->back()->with('error', 'Booking not found or already returned.');
        }
        $odo = (int) $this->input('odometer_in', 0) ?: null;
        $this->db->table('loan_bookings')->where('id', $bookingId)->update(['returned_at' => $this->now(), 'odometer_in' => $odo, 'fuel_in' => $this->input('fuel_in'), 'updated_at' => $this->now()]);
        $this->db->table('loan_vehicles')->where('id', $b['loan_vehicle_id'])->update(['status' => 'available'] + ($odo ? ['odometer' => $odo] : []));
        if ($b['job_card_id']) {
            $this->db->table('job_cards')->where('id', $b['job_card_id'])->update(['loan_vehicle_id' => null]);
        }
        return redirect()->back()->with('success', 'Loan vehicle returned.');
    }
}
