<?php

namespace App\Libraries;

use Config\Database;

/**
 * The Monday-morning management email: previous week (Mon–Sun) vs the week before,
 * month-to-date vs the same days last month, charts, leaderboards and cash position.
 */
class WeeklyReport
{
    public static function periods(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday last week', strtotime($today)));
        $weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $prevStart = date('Y-m-d', strtotime($weekStart . ' -7 days'));
        $prevEnd   = date('Y-m-d', strtotime($weekEnd . ' -7 days'));
        $mtdEnd    = date('Y-m-d', strtotime($today . ' -1 day'));
        $mtdStart  = date('Y-m-01', strtotime($mtdEnd));
        $days      = (int) date('j', strtotime($mtdEnd)) - 1;
        $lmStart   = date('Y-m-01', strtotime($mtdStart . ' -1 month'));
        $lmEnd     = date('Y-m-d', min(strtotime($lmStart . " +{$days} days"), strtotime(date('Y-m-t', strtotime($lmStart)))));
        return compact('weekStart', 'weekEnd', 'prevStart', 'prevEnd', 'mtdStart', 'mtdEnd', 'lmStart', 'lmEnd');
    }

    public static function build(?string $today = null): array
    {
        $p = self::periods($today);
        $db = Database::connect();
        $week = Reports::summary($p['weekStart'], $p['weekEnd']);
        $prev = Reports::summary($p['prevStart'], $p['prevEnd']);
        $mtd  = Reports::summary($p['mtdStart'], $p['mtdEnd']);
        $lm   = Reports::summary($p['lmStart'], $p['lmEnd']);
        $daily = Reports::series($p['weekStart'], $p['weekEnd'], 'day');
        $daily['labels'] = array_map(static fn ($d) => date('D', strtotime($d)), array_map(static fn ($i) => date('Y-m-d', strtotime($p['weekStart'] . " +{$i} days")), array_keys($daily['labels'])));

        $aged = Reports::agedDebtors();
        $sum = static fn ($k) => array_sum(array_column($aged, $k));
        $workshop = array_column($db->table('job_cards')->select('status, COUNT(*) n')->where('deleted_at', null)
            ->whereIn('status', ['checked_in', 'in_progress', 'awaiting_parts', 'awaiting_approval', 'quality_check', 'completed'])->groupBy('status')->get()->getResultArray(), 'n', 'status');

        return [
            'p' => $p, 'week' => $week, 'prev' => $prev, 'mtd' => $mtd, 'lm' => $lm, 'daily' => $daily,
            'mix' => Reports::revenueByType($p['mtdStart'], $p['mtdEnd']),
            'techs' => array_slice(Reports::mechanicProductivity($p['weekStart'], $p['weekEnd']), 0, 5),
            'services' => Reports::topServices($p['weekStart'], $p['weekEnd'], 5),
            'methods' => Reports::paymentsByMethod($p['weekStart'], $p['weekEnd']),
            'debtors' => ['total' => $sum('total'), 'current' => $sum('current'), 'overdue' => $sum('total') - $sum('current'), 'd90' => $sum('d90') + $sum('d90plus'), 'top' => array_slice($aged, 0, 5)],
            'workshop' => $workshop,
            'vat' => Reports::vat($p['mtdStart'], $p['mtdEnd']),
            'lowStock' => count(Reports::lowStock(100)),
        ];
    }

    /**
     * Send the report. Without $force it only sends once per ISO week.
     */
    public static function send(bool $force = false, ?string $recipients = null): bool
    {
        if (!$force && (int) Settings::get('weekly_report_enabled', 1) !== 1) {
            return false;
        }
        $isoWeek = date('o-W');
        if (!$force && Settings::get('weekly_report_last_week') === $isoWeek) {
            return false;
        }
        $to = $recipients ?? (string) Settings::get('weekly_report_recipients', '');
        if (trim($to) === '') {
            return false;
        }
        $data = self::build();
        $p = $data['p'];

        $attachments = [
            ['content' => ChartImage::bars($data['daily']['labels'], $data['daily']['revenue']), 'name' => 'daily-revenue.png', 'mime' => 'image/png', 'inline' => true],
            ['content' => ChartImage::doughnut($data['mix']), 'name' => 'revenue-mix.png', 'mime' => 'image/png', 'inline' => true],
            ['content' => Pdf::render('pdf/weekly_report', $data), 'name' => 'Wheelsturn-Weekly-Report-' . $p['weekStart'] . '.pdf', 'mime' => 'application/pdf'],
        ];
        $subject = 'Weekly performance · ' . date('d M', strtotime($p['weekStart'])) . ' – ' . date('d M Y', strtotime($p['weekEnd'])) . ' · ' . money_short($data['week']['revenue']) . ' revenue';
        $ok = Mailer::send($to, $subject, view('emails/weekly_report', $data), $attachments, 'report', null, null, 'emails/layout_wide');
        if ($ok) {
            Settings::set('weekly_report_last_week', $isoWeek, 'notifications');
            Settings::set('weekly_report_last_sent', date('Y-m-d H:i:s'), 'notifications');
        }
        return $ok;
    }
}
