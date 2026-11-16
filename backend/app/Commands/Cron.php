<?php

namespace App\Commands;

use App\Libraries\Billing;
use App\Libraries\Settings;
use App\Libraries\WeeklyReport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Single scheduler entry point for cPanel. Add ONE cron job (every 15 minutes):
 *   php /home/USER/path/to/backend/spark gms:cron
 */
class Cron extends BaseCommand
{
    protected $group       = 'Wheelsturn';
    protected $name        = 'gms:cron';
    protected $description = 'Runs scheduled tasks: Monday report, payment reminders, quote expiry.';

    public function run(array $params)
    {
        helper(['url', 'app', 'number', 'text']);
        $now = time();

        // 0. Apply database migrations from newly deployed code (no Terminal needed after "Update from Remote")
        try {
            $migrations = service('migrations');
            $migrations->setNamespace(null);
            $migrations->latest();
        } catch (\Throwable $e) {
            CLI::error('Migration failed: ' . $e->getMessage());
            log_message('critical', 'Auto-migration failed: ' . $e->getMessage());
        }

        // 1. Monday morning performance report (07:00 onwards, once per week)
        if (date('N', $now) === '1' && (int) date('G', $now) >= 7) {
            $ok = WeeklyReport::send(false);
            CLI::write('Weekly report: ' . ($ok ? 'sent' : 'skipped / already sent'), $ok ? 'green' : 'yellow');
        }

        // 2. Daily tasks (once per day, after 08:00)
        if ((int) date('G', $now) >= 8 && Settings::get('cron_daily_last') !== date('Y-m-d')) {
            Settings::set('cron_daily_last', date('Y-m-d'), 'system');
            $this->expireQuotes();
            CLI::write('Service reminders: ' . \App\Libraries\Engagement::serviceReminders());
            CLI::write('Licence reminders: ' . \App\Libraries\Engagement::licenceReminders());
            CLI::write('Appointment reminders: ' . \App\Libraries\Engagement::appointmentReminders());
            CLI::write('Feedback requests: ' . \App\Libraries\Engagement::feedbackRequests());
            \App\Libraries\Engagement::lowStockAlert();
            if (date('j') === '1' && (int) Settings::get('auto_statements', 0) === 1) {
                CLI::write('Statements emailed: ' . \App\Libraries\Engagement::sendStatements());
            }
            if ((int) Settings::get('backup_enabled', 1) === 1) {
                \App\Libraries\Backup::run();
            }
            if ((int) Settings::get('auto_payment_reminders', 1) === 1) {
                $this->paymentReminders();
            }
        }
        CLI::write('Cron finished at ' . date('Y-m-d H:i:s'));
    }

    private function expireQuotes(): void
    {
        db_connect()->table('quotes')->whereIn('status', ['draft', 'sent'])->where('expiry_date <', date('Y-m-d'))->update(['status' => 'expired']);
    }

    private function paymentReminders(): void
    {
        $db = db_connect();
        $days = array_filter(array_map('intval', explode(',', (string) Settings::get('reminder_days_after_due', '3,7,14'))));
        $sent = 0;
        foreach ($days as $n) {
            $due = date('Y-m-d', strtotime("-{$n} days"));
            $rows = $db->table('invoices i')->select('i.id')->join('customers c', 'c.id = i.customer_id')
                ->where('i.deleted_at', null)->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date', $due)
                ->where('c.email IS NOT NULL')->where('c.email !=', '')->get()->getResultArray();
            foreach ($rows as $r) {
                if (Billing::sendInvoice((int) $r['id'], null, 'reminder')) {
                    $sent++;
                }
            }
        }
        CLI::write("Payment reminders sent: {$sent}");
    }
}
