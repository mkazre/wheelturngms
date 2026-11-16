<?php

namespace App\Libraries;

use Config\Database;

/**
 * Reporting engine used by the dashboard, the reports module, exports and the Monday email.
 * Kept database-portable (MySQL & SQLite): date bucketing is done in PHP.
 */
class Reports
{
    private static function db()
    {
        return Database::connect();
    }

    private static function invoiceBase(string $from, string $to, ?int $branchId = null)
    {
        $b = self::db()->table('invoices i')
            ->where('i.deleted_at', null)->whereNotIn('i.status', ['draft', 'void'])
            ->where('i.issue_date >=', $from)->where('i.issue_date <=', $to);
        if ($branchId) {
            $b->where('i.branch_id', $branchId);
        }
        return $b;
    }

    /** Headline KPIs for a period. */
    public static function summary(string $from, string $to, ?int $branchId = null): array
    {
        $db = self::db();
        $inv = self::invoiceBase($from, $to, $branchId)
            ->select('COUNT(*) n, COALESCE(SUM(i.subtotal - i.discount_total),0) net, COALESCE(SUM(i.vat_total),0) vat, COALESCE(SUM(i.total),0) gross, COALESCE(SUM(i.cost_total),0) cost')
            ->get()->getRowArray();

        $pay = $db->table('payments')->select('COUNT(*) n, COALESCE(SUM(amount),0) amt')->where('status', 'completed')
            ->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59');
        if ($branchId) {
            $pay->where('branch_id', $branchId);
        }
        $pay = $pay->get()->getRowArray();

        $jobsOpened = $db->table('job_cards')->where('deleted_at', null)->where('created_at >=', $from . ' 00:00:00')->where('created_at <=', $to . ' 23:59:59');
        $jobsDone   = $db->table('job_cards')->where('deleted_at', null)->where('completed_at >=', $from . ' 00:00:00')->where('completed_at <=', $to . ' 23:59:59');
        if ($branchId) {
            $jobsOpened->where('branch_id', $branchId);
            $jobsDone->where('branch_id', $branchId);
        }
        $newCustomers = $db->table('customers')->where('deleted_at', null)->where('created_at >=', $from . ' 00:00:00')->where('created_at <=', $to . ' 23:59:59')->countAllResults();

        $quotes = $db->table('quotes')->select("COUNT(*) n, SUM(CASE WHEN status IN ('accepted','converted') THEN 1 ELSE 0 END) won")
            ->where('deleted_at', null)->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getRowArray();

        // Credit notes reduce revenue; cost of sales and overheads come from the ledger so that
        // supplier bills, journals and expenses are all included (matches the P&L exactly).
        $cn = $db->table('credit_notes')->select('COALESCE(SUM(subtotal - discount_total),0) net, COALESCE(SUM(vat_total),0) vat', false)
            ->where(['status' => 'issued', 'deleted_at' => null])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getRowArray();
        $ledger = $db->table('journal_lines l')->select("a.sub_type, COALESCE(SUM(l.debit - l.credit),0) amt", false)
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->join('accounts a', 'a.id = l.account_id')
            ->where('a.type', 'expense')->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)
            ->groupBy('a.sub_type')->get()->getResultArray();
        $cos = $opex = 0.0;
        foreach ($ledger as $l) {
            if ($l['sub_type'] === 'cost_of_sales') {
                $cos += (float) $l['amt'];
            } else {
                $opex += (float) $l['amt'];
            }
        }
        $net    = (float) $inv['net'] - (float) $cn['net'];
        $inv['vat'] = (float) $inv['vat'] - (float) $cn['vat'];
        $cost   = $cos;
        $expEx  = $opex;
        return [
            'invoices'        => (int) $inv['n'],
            'revenue'         => $net,
            'vat'             => (float) $inv['vat'],
            'gross'           => (float) $inv['gross'],
            'cost_of_sales'   => $cost,
            'gross_profit'    => $net - $cost,
            'gross_margin'    => $net > 0 ? ($net - $cost) / $net * 100 : 0,
            'expenses'        => $expEx,
            'net_profit'      => $net - $cost - $expEx,
            'collected'       => (float) $pay['amt'],
            'payments'        => (int) $pay['n'],
            'jobs_opened'     => $jobsOpened->countAllResults(),
            'jobs_completed'  => $jobsDone->countAllResults(),
            'new_customers'   => $newCustomers,
            'avg_invoice'     => (int) $inv['n'] > 0 ? (float) $inv['gross'] / (int) $inv['n'] : 0,
            'quotes'          => (int) $quotes['n'],
            'quote_win_rate'  => (int) $quotes['n'] > 0 ? (int) $quotes['won'] / (int) $quotes['n'] * 100 : 0,
        ];
    }

    /** Percentage change helper. */
    public static function delta(float $now, float $before): ?float
    {
        if ($before == 0.0) {
            return $now == 0.0 ? 0.0 : null;
        }
        return ($now - $before) / abs($before) * 100;
    }

    /**
     * Revenue / collections / expenses series bucketed by day, week or month.
     */
    public static function series(string $from, string $to, string $bucket = 'day', ?int $branchId = null): array
    {
        $fmt = ['day' => 'Y-m-d', 'week' => 'o-\WW', 'month' => 'Y-m'][$bucket];
        $lbl = ['day' => 'd M', 'week' => '\W\k W', 'month' => 'M Y'][$bucket];
        $labels = $keys = [];
        $step = ['day' => '+1 day', 'week' => '+1 week', 'month' => 'first day of next month'][$bucket];
        $cur = strtotime($bucket === 'month' ? date('Y-m-01', strtotime($from)) : ($bucket === 'week' ? date('Y-m-d', strtotime('monday this week', strtotime($from))) : $from));
        $end = strtotime($to);
        while ($cur <= $end) {
            $k = date($fmt, $cur);
            $keys[$k] = 0.0;
            $labels[$k] = date($lbl, $cur);
            $cur = strtotime($step, $cur);
        }
        $revenue = $collected = $expenses = $keys;

        foreach (self::invoiceBase($from, $to, $branchId)->select('i.issue_date d, (i.subtotal - i.discount_total) v')->get()->getResultArray() as $r) {
            $k = date($fmt, strtotime($r['d']));
            if (isset($revenue[$k])) {
                $revenue[$k] += (float) $r['v'];
            }
        }
        $p = self::db()->table('payments')->select('paid_at d, amount v')->where('status', 'completed')
            ->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59');
        if ($branchId) {
            $p->where('branch_id', $branchId);
        }
        foreach ($p->get()->getResultArray() as $r) {
            $k = date($fmt, strtotime($r['d']));
            if (isset($collected[$k])) {
                $collected[$k] += (float) $r['v'];
            }
        }
        $e = self::db()->table('expenses')->select('expense_date d, amount_excl v')->where('deleted_at', null)
            ->where('expense_date >=', $from)->where('expense_date <=', $to);
        foreach ($e->get()->getResultArray() as $r) {
            $k = date($fmt, strtotime($r['d']));
            if (isset($expenses[$k])) {
                $expenses[$k] += (float) $r['v'];
            }
        }
        return [
            'labels'    => array_values($labels),
            'revenue'   => array_map(static fn ($v) => round($v, 2), array_values($revenue)),
            'collected' => array_map(static fn ($v) => round($v, 2), array_values($collected)),
            'expenses'  => array_map(static fn ($v) => round($v, 2), array_values($expenses)),
        ];
    }

    /** Net revenue split by line type (labour, parts, ...). */
    public static function revenueByType(string $from, string $to): array
    {
        $rows = self::invoiceBase($from, $to)
            ->select('li.item_type, COALESCE(SUM(li.line_subtotal - li.line_discount),0) amount')
            ->join('line_items li', "li.document_type = 'invoice' AND li.document_id = i.id")
            ->groupBy('li.item_type')->orderBy('amount', 'DESC')->get()->getResultArray();
        return array_map(static fn ($r) => ['label' => LineItems::TYPES[$r['item_type']] ?? ucfirst($r['item_type']), 'value' => round((float) $r['amount'], 2)], $rows);
    }

    public static function paymentsByMethod(string $from, string $to): array
    {
        $rows = self::db()->table('payments')->select('method, COALESCE(SUM(amount),0) amount, COUNT(*) n')
            ->where('status', 'completed')->where('paid_at >=', $from . ' 00:00:00')->where('paid_at <=', $to . ' 23:59:59')
            ->groupBy('method')->orderBy('amount', 'DESC')->get()->getResultArray();
        return array_map(static fn ($r) => ['label' => strtoupper($r['method']), 'value' => round((float) $r['amount'], 2), 'count' => (int) $r['n']], $rows);
    }

    public static function jobsByStatus(): array
    {
        $rows = self::db()->table('job_cards')->select('status, COUNT(*) n')->where('deleted_at', null)
            ->whereNotIn('status', ['collected', 'cancelled'])->groupBy('status')->get()->getResultArray();
        $map = array_column($rows, 'n', 'status');
        $out = [];
        foreach (job_statuses() as $k => $s) {
            if (!in_array($k, ['collected', 'cancelled'], true)) {
                $out[] = ['key' => $k, 'label' => $s['label'], 'value' => (int) ($map[$k] ?? 0), 'color' => $s['hex']];
            }
        }
        return $out;
    }

    public static function topServices(string $from, string $to, int $limit = 10): array
    {
        return self::invoiceBase($from, $to)
            ->select('li.description, li.item_type, SUM(li.quantity) qty, SUM(li.line_subtotal - li.line_discount) amount')
            ->join('line_items li', "li.document_type = 'invoice' AND li.document_id = i.id")
            ->groupBy('li.description, li.item_type')->orderBy('amount', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public static function topCustomers(string $from, string $to, int $limit = 10): array
    {
        return self::invoiceBase($from, $to)
            ->select("c.id, CASE WHEN c.type = 'business' AND c.company_name <> '' THEN c.company_name ELSE CONCAT_WS(' ', c.first_name, c.last_name) END name, COUNT(i.id) invoices, SUM(i.total) amount", false)
            ->join('customers c', 'c.id = i.customer_id')
            ->groupBy('c.id, c.type, c.company_name, c.first_name, c.last_name')->orderBy('amount', 'DESC')->limit($limit)->get()->getResultArray();
    }

    public static function expensesByAccount(string $from, string $to): array
    {
        return self::db()->table('expenses e')->select('a.code, a.name, COUNT(e.id) n, SUM(e.amount_excl) excl, SUM(e.vat_amount) vat, SUM(e.total) total')
            ->join('accounts a', 'a.id = e.account_id')->where('e.deleted_at', null)
            ->where('e.expense_date >=', $from)->where('e.expense_date <=', $to)
            ->groupBy('a.code, a.name')->orderBy('total', 'DESC')->get()->getResultArray();
    }

    /** Labour hours logged & labour revenue per mechanic. */
    public static function mechanicProductivity(string $from, string $to): array
    {
        $db = self::db();
        $mechs = $db->table('users')->select('id, first_name, last_name, hourly_rate, cost_rate')
            ->whereIn('role', ['mechanic', 'manager'])->where('deleted_at', null)->get()->getResultArray();
        $time = array_column($db->table('time_logs')->select('user_id, SUM(minutes) m')
            ->where('started_at >=', $from . ' 00:00:00')->where('started_at <=', $to . ' 23:59:59')
            ->groupBy('user_id')->get()->getResultArray(), 'm', 'user_id');
        $rev = array_column(self::invoiceBase($from, $to)->select('li.mechanic_id, SUM(li.line_subtotal - li.line_discount) amt, SUM(CASE WHEN li.item_type = \'labour\' THEN li.quantity ELSE 0 END) hrs')
            ->join('line_items li', "li.document_type = 'invoice' AND li.document_id = i.id")
            ->where('li.mechanic_id IS NOT NULL')->groupBy('li.mechanic_id')->get()->getResultArray(), null, 'mechanic_id');
        $jobs = array_column($db->table('job_card_mechanics jm')->select('jm.user_id, COUNT(DISTINCT jm.job_card_id) n')
            ->join('job_cards j', 'j.id = jm.job_card_id')->where('j.completed_at >=', $from . ' 00:00:00')->where('j.completed_at <=', $to . ' 23:59:59')
            ->groupBy('jm.user_id')->get()->getResultArray(), 'n', 'user_id');

        $out = [];
        foreach ($mechs as $m) {
            $hoursWorked = round(((int) ($time[$m['id']] ?? 0)) / 60, 2);
            $hoursSold   = round((float) ($rev[$m['id']]['hrs'] ?? 0), 2);
            $out[] = [
                'name'         => trim($m['first_name'] . ' ' . $m['last_name']),
                'jobs'         => (int) ($jobs[$m['id']] ?? 0),
                'hours_worked' => $hoursWorked,
                'hours_sold'   => $hoursSold,
                'efficiency'   => $hoursWorked > 0 ? round($hoursSold / $hoursWorked * 100, 1) : 0,
                'revenue'      => round((float) ($rev[$m['id']]['amt'] ?? 0), 2),
            ];
        }
        usort($out, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /** Aged debtors as at today: current / 1-30 / 31-60 / 61-90 / 90+ days overdue. */
    public static function agedDebtors(): array
    {
        $rows = self::db()->table('invoices i')
            ->select("i.customer_id, i.due_date, i.balance_due, CASE WHEN c.type = 'business' AND c.company_name <> '' THEN c.company_name ELSE CONCAT_WS(' ', c.first_name, c.last_name) END name, c.phone, c.email", false)
            ->join('customers c', 'c.id = i.customer_id')
            ->where('i.deleted_at', null)->whereIn('i.status', ['unpaid', 'partial'])->where('i.balance_due >', 0)
            ->get()->getResultArray();
        $out = [];
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $r) {
            $id = $r['customer_id'];
            $out[$id] ??= ['customer_id' => $id, 'name' => $r['name'], 'phone' => $r['phone'], 'email' => $r['email'], 'current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90plus' => 0, 'total' => 0];
            $days = (int) floor(($today - strtotime($r['due_date'])) / 86400);
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'd90plus')));
            $out[$id][$bucket] += (float) $r['balance_due'];
            $out[$id]['total'] += (float) $r['balance_due'];
        }
        usort($out, static fn ($a, $b) => $b['total'] <=> $a['total']);
        return $out;
    }

    /** VAT201-style summary. */
    /** VAT201-style summary from the ledger (includes credit notes, bills and bank-created entries). */
    public static function vat(string $from, string $to): array
    {
        $c = VatReturns::calculate($from, $to);
        return $c + ['payable' => $c['net_vat']];
    }

    public static function lowStock(int $limit = 10): array
    {
        return self::db()->table('products')->where(['track_stock' => 1, 'is_active' => 1, 'deleted_at' => null])
            ->where('qty_on_hand <= reorder_level', null, false)->orderBy('qty_on_hand')->limit($limit)->get()->getResultArray();
    }
}
