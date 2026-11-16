<?php

namespace App\Libraries;

use Config\Database;

/**
 * Customer engagement automation: service / licence / appointment reminders, feedback requests,
 * monthly statements and low-stock alerts. Run daily from gms:cron.
 */
class Engagement
{
    private static function db()
    {
        return Database::connect();
    }

    /** Send an email and/or SMS to a customer according to settings. Returns true if anything was sent. */
    public static function reach(array $customer, string $subject, string $html, string $sms, ?string $entityType = null, ?int $entityId = null): bool
    {
        $sent = false;
        if (!empty($customer['email'])) {
            $sent = Mailer::send($customer['email'], $subject, $html, [], $entityType, $entityId) || $sent;
        }
        if ((int) Settings::get('sms_reminders', 0) === 1 && !empty($customer['phone']) && (int) ($customer['sms_consent'] ?? 1) === 1) {
            $sent = Notify::sms($customer['phone'], $sms, $entityType, $entityId) || $sent;
        }
        return $sent;
    }

    public static function serviceReminders(): int
    {
        if ((int) Settings::get('service_reminders', 1) !== 1) {
            return 0;
        }
        $days = (int) Settings::get('service_reminder_days', 14);
        $rows = self::db()->table('vehicles v')->select('v.*, c.id cid, c.first_name, c.last_name, c.company_name, c.type, c.email, c.phone, c.sms_consent')
            ->join('customers c', 'c.id = v.customer_id')->where(['v.deleted_at' => null, 'c.deleted_at' => null, 'c.marketing_consent' => 1])
            ->where('v.next_service_date IS NOT NULL')->where('v.next_service_date <=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('v.next_service_date >=', date('Y-m-d', strtotime('-30 days')))
            ->groupStart()->where('v.service_reminder_sent_at', null)->orWhere('v.service_reminder_sent_at <', date('Y-m-d', strtotime('-60 days')))->groupEnd()
            ->get()->getResultArray();
        $n = 0;
        foreach ($rows as $v) {
            $car = trim($v['make'] . ' ' . $v['model']) . ' (' . $v['registration'] . ')';
            $html = '<p>Hi ' . esc(customer_name($v)) . ',</p><p>Your <b>' . esc($car) . '</b> is due for its service on <b>' . fdate($v['next_service_date']) . '</b>'
                . ($v['next_service_km'] ? ' or at ' . number_format($v['next_service_km']) . ' km' : '') . '. Regular servicing protects your warranty, safety and resale value.</p>'
                . view('emails/_pay_button', ['url' => site_url('book'), 'label' => 'Book your service online']);
            if (self::reach(['email' => $v['email'], 'phone' => $v['phone'], 'sms_consent' => $v['sms_consent']], 'Your ' . $v['make'] . ' is due for a service', $html,
                Settings::get('company_name') . ': your ' . $car . ' is due for a service on ' . fdate($v['next_service_date']) . '. Book online: ' . site_url('book'), 'vehicle', (int) $v['id'])) {
                self::db()->table('vehicles')->where('id', $v['id'])->update(['service_reminder_sent_at' => date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        return $n;
    }

    public static function licenceReminders(): int
    {
        if ((int) Settings::get('licence_reminders', 1) !== 1) {
            return 0;
        }
        $rows = self::db()->table('vehicles v')->select('v.*, c.first_name, c.last_name, c.company_name, c.type, c.email, c.phone, c.sms_consent')
            ->join('customers c', 'c.id = v.customer_id')->where(['v.deleted_at' => null, 'c.deleted_at' => null])
            ->where('v.licence_expiry IS NOT NULL')->where('v.licence_expiry <=', date('Y-m-d', strtotime('+21 days')))->where('v.licence_expiry >=', date('Y-m-d'))
            ->groupStart()->where('v.licence_reminder_sent_at', null)->orWhere('v.licence_reminder_sent_at <', date('Y-m-d', strtotime('-300 days')))->groupEnd()
            ->get()->getResultArray();
        $n = 0;
        foreach ($rows as $v) {
            $html = '<p>Hi ' . esc(customer_name($v)) . ',</p><p>A friendly reminder that the licence disc for <b>' . esc($v['registration']) . '</b> expires on <b>' . fdate($v['licence_expiry']) . '</b>. Driving with an expired disc can lead to fines.</p><p>Need a roadworthy or service before renewal? We can help.</p>'
                . view('emails/_pay_button', ['url' => site_url('book'), 'label' => 'Book a roadworthy / service']);
            if (self::reach($v, 'Licence disc renewal reminder: ' . $v['registration'], $html, Settings::get('company_name') . ': licence disc for ' . $v['registration'] . ' expires ' . fdate($v['licence_expiry']) . '.', 'vehicle', (int) $v['id'])) {
                self::db()->table('vehicles')->where('id', $v['id'])->update(['licence_reminder_sent_at' => date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        return $n;
    }

    /** Day-before reminders for booked job cards. */
    public static function appointmentReminders(): int
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $rows = self::db()->table('job_cards j')->select('j.id, j.number, j.booked_for, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type, c.email, c.phone, c.sms_consent')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->where(['j.deleted_at' => null, 'j.status' => 'booked'])->where('j.booked_for >=', $tomorrow . ' 00:00:00')->where('j.booked_for <=', $tomorrow . ' 23:59:59')
            ->where("j.id NOT IN (SELECT entity_id FROM email_logs WHERE entity_type = 'appointment')", null, false)->get()->getResultArray();
        $n = 0;
        foreach ($rows as $j) {
            $when = date('D d M \a\t H:i', strtotime($j['booked_for']));
            $html = '<p>Hi ' . esc(customer_name($j)) . ',</p><p>See you tomorrow! Your <b>' . esc(trim($j['make'] . ' ' . $j['model'])) . '</b> (' . esc($j['registration']) . ') is booked in on <b>' . $when . '</b>.</p><p>' . nl2br(esc(Settings::get('company_address'))) . '</p><p>Need to reschedule? Call ' . esc(Settings::get('company_phone')) . '.</p>';
            if (self::reach($j, 'Reminder: your booking tomorrow at ' . date('H:i', strtotime($j['booked_for'])), $html, Settings::get('company_name') . ': reminder of your booking ' . $when . ' for ' . $j['registration'] . '. Call ' . Settings::get('company_phone') . ' to reschedule.', 'appointment', (int) $j['id'])) {
                $n++;
            }
        }
        return $n;
    }

    /** Ask for feedback one day after collection. */
    public static function feedbackRequests(): int
    {
        if ((int) Settings::get('feedback_requests', 1) !== 1) {
            return 0;
        }
        $rows = self::db()->table('job_cards j')->select('j.id, j.number, j.customer_id, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type, c.email, c.phone, c.sms_consent')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->where(['j.deleted_at' => null, 'j.status' => 'collected'])->where('j.collected_at >=', date('Y-m-d', strtotime('-4 days')))->where('j.collected_at <=', date('Y-m-d H:i:s', strtotime('-20 hours')))
            ->where('j.id NOT IN (SELECT job_card_id FROM feedback WHERE job_card_id IS NOT NULL)', null, false)->get()->getResultArray();
        $n = 0;
        foreach ($rows as $j) {
            $token = bin2hex(random_bytes(16));
            self::db()->table('feedback')->insert(['job_card_id' => $j['id'], 'customer_id' => $j['customer_id'], 'token' => $token, 'sent_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $url = site_url('feedback/' . $token);
            $scale = '<table role="presentation" cellpadding="0" cellspacing="4" style="margin:16px 0;"><tr>';
            for ($i = 0; $i <= 10; $i++) {
                $bg = $i <= 6 ? '#fee2e2' : ($i <= 8 ? '#fef3c7' : '#d1fae5');
                $scale .= '<td><a href="' . $url . '?score=' . $i . '" style="display:inline-block;width:30px;height:30px;line-height:30px;text-align:center;border-radius:8px;background:' . $bg . ';color:#0b0d2e;font-weight:700;text-decoration:none;">' . $i . '</a></td>';
            }
            $scale .= '</tr></table>';
            $html = '<p>Hi ' . esc(customer_name($j)) . ',</p><p>Thank you for trusting us with your <b>' . esc(trim($j['make'] . ' ' . $j['model'])) . '</b>. How likely are you to recommend ' . esc(Settings::get('company_name')) . ' to a friend?</p>' . $scale . '<p style="font-size:12px;color:#64748b">0 = not at all likely, 10 = extremely likely. It takes 10 seconds.</p>';
            if (self::reach($j, 'How did we do? ' . $j['number'], $html, Settings::get('company_name') . ': thanks for your visit! Rate us in 10 seconds: ' . $url, 'feedback', (int) $j['id'])) {
                $n++;
            } else {
                self::db()->table('feedback')->where('token', $token)->delete(); // retry tomorrow
            }
        }
        return $n;
    }

    /** Email statements to every customer with an outstanding balance. */
    public static function sendStatements(): int
    {
        $n = 0;
        foreach (Reports::agedDebtors() as $d) {
            $c = self::db()->table('customers')->where('id', $d['customer_id'])->get()->getRowArray();
            if (!$c || !$c['email'] || $d['total'] <= 0) {
                continue;
            }
            $from = date('Y-m-d', strtotime('-6 months'));
            $pdf = self::statementPdf($c, $from, date('Y-m-d'));
            $ok = Mailer::send($c['email'], 'Statement of account: ' . Settings::get('company_name'),
                '<p>Hi ' . esc(customer_name($c)) . ',</p><p>Please find your statement attached. Outstanding balance: <b>' . money($d['total']) . '</b>' . ($d['total'] - $d['current'] > 0 ? ', of which <b>' . money($d['total'] - $d['current']) . '</b> is overdue' : '') . '.</p><p>Banking details are on the statement. Thank you for your prompt payment.</p>',
                [['content' => $pdf, 'name' => 'Statement-' . ($c['code'] ?: $c['id']) . '.pdf']], 'customer', (int) $c['id']);
            if ($ok) {
                self::db()->table('customers')->where('id', $c['id'])->update(['last_statement_sent_at' => date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        return $n;
    }

    public static function statementPdf(array $c, string $from, string $to): string
    {
        $db = self::db();
        $id = (int) $c['id'];
        $invBefore = (float) $db->table('invoices')->selectSum('total')->where(['customer_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->where('issue_date <', $from)->get()->getRow()->total;
        $payBefore = (float) $db->table('payments')->selectSum('amount')->where(['customer_id' => $id, 'status' => 'completed'])->where('paid_at <', $from . ' 00:00:00')->get()->getRow()->amount;
        $cnBefore = (float) $db->table('credit_notes')->selectSum('total')->where(['customer_id' => $id, 'status' => 'issued'])->where('issue_date <', $from)->get()->getRow()->total;
        $opening = $invBefore - $payBefore - $cnBefore;
        $lines = [];
        foreach ($db->table('invoices')->where(['customer_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getResultArray() as $i) {
            $lines[] = ['date' => $i['issue_date'], 'ref' => $i['number'], 'desc' => 'Tax invoice', 'debit' => (float) $i['total'], 'credit' => 0];
        }
        foreach ($db->table('payments')->where(['customer_id' => $id, 'status' => 'completed'])->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59')->get()->getResultArray() as $p) {
            $lines[] = ['date' => substr($p['paid_at'], 0, 10), 'ref' => $p['number'], 'desc' => 'Payment – ' . strtoupper($p['method']), 'debit' => 0, 'credit' => (float) $p['amount']];
        }
        foreach ($db->table('credit_notes')->where(['customer_id' => $id, 'status' => 'issued'])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getResultArray() as $cn) {
            $lines[] = ['date' => $cn['issue_date'], 'ref' => $cn['number'], 'desc' => 'Credit note', 'debit' => 0, 'credit' => (float) $cn['total']];
        }
        usort($lines, static fn ($a, $b) => strcmp($a['date'], $b['date']));
        $bal = $opening;
        foreach ($lines as &$l) {
            $bal += $l['debit'] - $l['credit'];
            $l['balance'] = $bal;
        }
        unset($l);
        $ageing = array_values(array_filter(Reports::agedDebtors(), static fn ($r) => (int) $r['customer_id'] === $id))[0] ?? null;
        return Pdf::render('pdf/statement', ['customer' => $c, 'from' => $from, 'to' => $to, 'opening' => $opening, 'lines' => $lines, 'closing' => $bal, 'ageing' => $ageing, 'company' => Settings::all()]);
    }

    public static function lowStockAlert(): void
    {
        $low = Reports::lowStock(50);
        if ($low) {
            Notify::staff(count($low) . ' items at or below reorder level', implode(', ', array_slice(array_column($low, 'name'), 0, 5)), 'stock/reorder', 'box');
        }
    }
}
