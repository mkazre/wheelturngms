<?php

namespace App\Controllers;

use App\Libraries\Reports;

class Dashboard extends BaseController
{
    public const PERIODS = [
        '7d'  => 'Last 7 days',
        '30d' => 'Last 30 days',
        'mtd' => 'Month to date',
        'qtd' => 'Quarter to date',
        'ytd' => 'Year to date',
        '12m' => 'Last 12 months',
    ];

    /** Returns [from, to, prevFrom, prevTo, bucket] */
    public static function periodRange(string $p): array
    {
        $today = date('Y-m-d');
        [$from, $bucket] = match ($p) {
            '7d'    => [date('Y-m-d', strtotime('-6 days')), 'day'],
            '30d'   => [date('Y-m-d', strtotime('-29 days')), 'day'],
            'qtd'   => [date('Y-') . str_pad((string) (intdiv((int) date('n') - 1, 3) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01', 'week'],
            'ytd'   => [date('Y-01-01'), 'month'],
            '12m'   => [date('Y-m-01', strtotime('-11 months')), 'month'],
            default => [date('Y-m-01'), 'day'],
        };
        $days = (int) round((strtotime($today) - strtotime($from)) / 86400);
        $prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . " -{$days} days"));
        return [$from, $today, $prevFrom, $prevTo, $bucket];
    }

    public function index()
    {
        $period = $this->request->getGet('period');
        $period = array_key_exists((string) $period, self::PERIODS) ? $period : 'mtd';
        [$from, $to, $pFrom, $pTo, $bucket] = self::periodRange($period);

        $now  = Reports::summary($from, $to);
        $prev = Reports::summary($pFrom, $pTo);

        $outstanding = $this->db->table('invoices')->selectSum('balance_due')->where('deleted_at', null)->whereIn('status', ['unpaid', 'partial'])->get()->getRow()->balance_due ?? 0;
        $overdue = $this->db->table('invoices i')->select('i.id, i.number, i.due_date, i.balance_due, c.first_name, c.last_name, c.company_name, c.type')
            ->join('customers c', 'c.id = i.customer_id')->where('i.deleted_at', null)->whereIn('i.status', ['unpaid', 'partial'])
            ->where('i.due_date <', date('Y-m-d'))->orderBy('i.due_date')->limit(6)->get()->getResultArray();
        $inWorkshop = $this->db->table('job_cards')->where('deleted_at', null)->whereIn('status', ['checked_in', 'in_progress', 'awaiting_parts', 'awaiting_approval', 'quality_check'])->countAllResults();
        $today = $this->db->table('job_cards j')->select('j.id, j.number, j.status, j.booked_for, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->where('j.deleted_at', null)->where('j.booked_for >=', date('Y-m-d 00:00:00'))->where('j.booked_for <=', date('Y-m-d 23:59:59'))
            ->orderBy('j.booked_for')->get()->getResultArray();
        $recent = $this->db->table('activity_logs a')->select('a.*, u.first_name, u.last_name')->join('users u', 'u.id = a.user_id', 'left')
            ->whereNotIn('a.action', ['login', 'logout'])->orderBy('a.id', 'DESC')->limit(8)->get()->getResultArray();

        return view('dashboard/index', [
            'period'      => $period,
            'from'        => $from,
            'to'          => $to,
            'now'         => $now,
            'prev'        => $prev,
            'outstanding' => (float) $outstanding,
            'inWorkshop'  => $inWorkshop,
            'overdue'     => $overdue,
            'todayJobs'   => $today,
            'recent'      => $recent,
            'series'      => Reports::series($from, $to, $bucket),
            'byType'      => Reports::revenueByType($from, $to),
            'byMethod'    => Reports::paymentsByMethod($from, $to),
            'jobStatus'   => Reports::jobsByStatus(),
            'topServices' => Reports::topServices($from, $to, 6),
            'mechanics'   => array_slice(Reports::mechanicProductivity($from, $to), 0, 5),
            'lowStock'    => Reports::lowStock(5),
            'target'      => (float) setting('monthly_revenue_target', 0),
            'mtdRevenue'  => Reports::summary(date('Y-m-01'), date('Y-m-d'))['revenue'],
            'myTasks'     => $this->db->table('tasks')->where(['assigned_to' => session('user_id'), 'status' => 'open'])->orderBy('due_at IS NULL', '', false)->orderBy('due_at')->limit(5)->get()->getResultArray(),
            'pendingBookings' => $this->db->table('bookings')->where('status', 'pending')->countAllResults(),
        ]);
    }
}
