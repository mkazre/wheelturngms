<?php

namespace App\Libraries;

use Config\Database;

/** Booking requests (public web form and customer app): confirm into a job card. */
class Bookings
{
    /** Confirm a booking: match/create customer + vehicle, create a booked job card. Returns the job id or null. */
    public static function confirm(int $id): ?int
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $b = $db->table('bookings')->where('id', $id)->get()->getRowArray();
        if (!$b || !in_array($b['status'], ['pending', 'confirmed'], true)) {
            return null;
        }
        $db->transStart();
        $phone = Notify::normalisePhone($b['phone']);
        $cust = $b['customer_id'] ? $db->table('customers')->where('id', $b['customer_id'])->get()->getRowArray() : null;
        if (!$cust && $b['email']) {
            $cust = $db->table('customers')->where(['email' => strtolower($b['email']), 'deleted_at' => null])->get()->getRowArray();
        }
        if (!$cust && $phone) {
            foreach ($db->table('customers')->select('id, phone')->where('deleted_at', null)->where('phone IS NOT NULL')->get()->getResultArray() as $c) {
                if (Notify::normalisePhone($c['phone']) === $phone) {
                    $cust = $c;
                    break;
                }
            }
        }
        if (!$cust) {
            [$first, $last] = array_pad(explode(' ', trim((string) $b['name']), 2), 2, '');
            $db->table('customers')->insert(['branch_id' => session('branch_id'), 'code' => Numbering::next('customer'), 'type' => 'individual', 'first_name' => $first, 'last_name' => $last, 'email' => $b['email'] ? strtolower($b['email']) : null, 'phone' => $b['phone'], 'source' => 'Online booking', 'popia_consent_at' => $b['created_at'], 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $cust = ['id' => (int) $db->insertID()];
        }
        $veh = $b['vehicle_id'] ? $db->table('vehicles')->where('id', $b['vehicle_id'])->get()->getRowArray() : null;
        $reg = strtoupper(trim((string) $b['registration']));
        if (!$veh && $reg) {
            $veh = $db->table('vehicles')->where(['registration' => $reg, 'deleted_at' => null])->get()->getRowArray();
        }
        if (!$veh) {
            [$make, $model] = array_pad(explode(' ', trim((string) $b['vehicle_desc']), 2), 2, '');
            $db->table('vehicles')->insert(['customer_id' => $cust['id'], 'registration' => $reg ?: 'TBC-' . $id, 'make' => $make ?: null, 'model' => $model ?: null, 'created_at' => $now, 'updated_at' => $now]);
            $veh = ['id' => (int) $db->insertID()];
        }
        $token = Billing::token();
        $db->table('job_cards')->insert([
            'number' => Numbering::next('job_card'), 'branch_id' => session('branch_id'), 'customer_id' => $cust['id'], 'vehicle_id' => $veh['id'], 'status' => 'booked', 'priority' => 'normal',
            'job_type' => $b['service'], 'customer_complaint' => trim(($b['service'] ? $b['service'] . '. ' : '') . $b['notes']), 'booked_for' => $b['preferred_at'],
            'public_token' => $token, 'advisor_id' => session('user_id'), 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $jobId = (int) $db->insertID();
        JobWorkflow::seed($jobId, (string) $b['service']);
        $db->table('bookings')->where('id', $id)->update(['status' => 'converted', 'customer_id' => $cust['id'], 'vehicle_id' => $veh['id'], 'job_card_id' => $jobId, 'updated_at' => $now]);
        $db->transComplete();
        Activity::log('job_card', $jobId, 'created', 'Created from a booking request');
        if ($b['email']) {
            Mailer::send($b['email'], 'Booking confirmed: ' . fdatetime($b['preferred_at']), '<p>Hi ' . esc($b['name']) . ',</p><p>Your booking at <b>' . esc(Settings::get('company_name')) . '</b> is <b>confirmed</b> for <b>' . fdatetime($b['preferred_at']) . '</b>.</p><p>' . nl2br(esc(Settings::get('company_address'))) . '</p>'
                . view('emails/_pay_button', ['url' => site_url('track/' . $token), 'label' => 'Track your vehicle']), [], 'job_card', $jobId);
        }
        Notify::customer((int) $cust['id'], 'Booking confirmed', 'See you on ' . fdatetime($b['preferred_at']) . '.', ['screen' => 'job', 'id' => $jobId]);
        return $jobId;
    }
}
