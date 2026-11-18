<?php

namespace App\Libraries;

use Config\Database;

/** Job-card status workflow shared by the web app and the mobile API. */
class JobWorkflow
{
    /** Customer-friendly push text for status changes worth telling the customer about. */
    private const CUSTOMER_MESSAGES = [
        'checked_in'        => 'Your vehicle has been checked in.',
        'in_progress'       => 'Our technician has started work on your vehicle.',
        'awaiting_approval' => 'We need your approval for some work. Tap to review.',
        'awaiting_parts'    => 'We are waiting for parts for your vehicle.',
        'completed'         => 'Your vehicle is ready for collection.',
    ];

    /** Seed a new job card with the inspection template and the matching technician checklists. */
    public static function seed(int $jobId, string $jobType): void
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $i = 0;
        foreach (preg_split('/\r?\n/', (string) Settings::get('inspection_template', '')) as $line) {
            if (($line = trim($line)) === '') {
                continue;
            }
            [$section, $item] = array_pad(explode('|', $line, 2), 2, null);
            if ($item === null) {
                [$section, $item] = ['General', $section];
            }
            $db->table('inspections')->insert(['job_card_id' => $jobId, 'section' => trim($section), 'item' => trim($item), 'sort_order' => $i++, 'created_at' => $now, 'updated_at' => $now]);
        }
        $tpls = $db->table('checklist_templates')->where('is_active', 1)
            ->groupStart()->where('job_type', null)->orWhere('job_type', '')->orWhere('job_type', $jobType)->groupEnd()->get()->getResultArray();
        $i = 0;
        foreach ($tpls as $t) {
            foreach (preg_split('/\r?\n/', (string) $t['items']) as $step) {
                if (($step = trim($step)) !== '') {
                    $db->table('job_checklist_items')->insert(['job_card_id' => $jobId, 'item' => mb_substr($step, 0, 255), 'sort_order' => $i++, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    /** Clock the current technician on/off a job. Returns [running(bool), message]. */
    public static function toggleTimer(int $jobId, int $userId): array
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $running = $db->table('time_logs')->where(['user_id' => $userId, 'ended_at' => null])->get()->getRowArray();
        if ($running) {
            $mins = max(1, (int) round((time() - strtotime($running['started_at'])) / 60));
            $db->table('time_logs')->where('id', $running['id'])->update(['ended_at' => $now, 'minutes' => $mins, 'updated_at' => $now]);
            Activity::log('job_card', (int) $running['job_card_id'], 'clock_off', 'Clocked off after ' . $mins . ' min');
            if ((int) $running['job_card_id'] === $jobId) {
                return [false, 'Clocked off · ' . $mins . ' minutes logged.'];
            }
        }
        $db->table('time_logs')->insert(['job_card_id' => $jobId, 'user_id' => $userId, 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $job = $db->table('job_cards')->where('id', $jobId)->get()->getRowArray();
        if (in_array($job['status'], ['booked', 'checked_in'], true)) {
            self::changeStatus($job, 'in_progress');
        }
        Activity::log('job_card', $jobId, 'clock_on', 'Clocked on');
        return [true, 'Clocked on. Timer running.'];
    }

    /** Apply a status change, timestamps, activity log, email and push notifications. Returns a message. */
    public static function changeStatus(array $job, string $status, int $odometerOut = 0): string
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $update = ['status' => $status, 'updated_at' => $now];
        $stamp = ['checked_in' => 'checked_in_at', 'in_progress' => 'started_at', 'completed' => 'completed_at', 'collected' => 'collected_at'];
        if (isset($stamp[$status]) && empty($job[$stamp[$status]])) {
            $update[$stamp[$status]] = $now;
        }
        if ($status === 'collected' && $odometerOut > 0) {
            $update['odometer_out'] = $odometerOut;
        }
        $db->table('job_cards')->where('id', $job['id'])->update($update);
        $label = job_statuses()[$status]['label'] ?? $status;
        Activity::log('job_card', (int) $job['id'], 'status', 'Status changed to ' . $label);

        $notified = false;
        if ($job['status'] !== $status) {
            if ($status === 'completed' && (int) Settings::get('notify_vehicle_ready', 1) === 1) {
                $notified = Billing::sendVehicleReady((int) $job['id']);
            }
            if (isset(self::CUSTOMER_MESSAGES[$status])) {
                $v = $db->table('vehicles')->select('registration')->where('id', $job['vehicle_id'])->get()->getRowArray();
                Notify::customer((int) $job['customer_id'], ($v['registration'] ?? 'Your vehicle') . ': ' . $label, self::CUSTOMER_MESSAGES[$status], ['screen' => 'job', 'id' => (int) $job['id']]);
            }
            if ($status === 'awaiting_parts' || $status === 'quality_check') {
                Notify::staff($job['number'] . ': ' . $label, '', 'job-cards/' . $job['id'], 'wrench', null, 'manager');
            }
        }
        return 'Status updated to ' . $label . ($notified ? ' · customer notified' : '');
    }
}
