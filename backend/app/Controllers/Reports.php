<?php

namespace App\Controllers;

use App\Libraries\Reports as R;
use App\Libraries\WeeklyReport;

class Reports extends BaseController
{
    /** Catalogue shown on the reports hub. */
    public const CATALOGUE = [
        'Sales & performance' => [
            'sales'        => ['Sales summary', 'Revenue trend, invoices and average sale', 'chart'],
            'revenue-mix'  => ['Revenue by type', 'Labour vs parts vs sundries', 'pie'],
            'top-services' => ['Top services & parts', 'Best sellers by value', 'wrench'],
            'customers'    => ['Top customers', 'Who spends the most', 'users'],
            'jobs'         => ['Job card register', 'All jobs, status and value', 'clipboard'],
            'technicians'  => ['Technician productivity', 'Hours worked vs sold, efficiency', 'zap'],
            'commissions'  => ['Technician commission & targets', 'Labour sold, commission earned, target progress', 'wallet'],
            'job-profitability' => ['Job profitability', 'Margin per job after parts & labour cost', 'chart'],
            'warranties'   => ['Warranty register', 'Parts & labour still under warranty, warranty claims', 'shield'],
            'kpi-benchmarks' => ['Workshop KPI benchmarks', 'Your numbers vs. well-run independent workshops', 'zap'],
        ],
        'Financial statements' => [
            'profit-loss'   => ['Profit & loss (income statement)', 'From the general ledger', 'book'],
            'balance-sheet' => ['Balance sheet', 'Assets, liabilities & equity', 'building'],
            'trial-balance' => ['Trial balance', 'All ledger accounts, debits and credits', 'book'],
            'cash-flow'     => ['Cash flow statement', 'Operating, investing & financing cash', 'wallet'],
            'general-ledger'=> ['General ledger detail', 'Every posting by account', 'clipboard'],
            'vat'           => ['VAT201 summary', 'Output vs input VAT for SARS', 'shield'],
        ],
        'Cash, debtors & creditors' => [
            'payments'     => ['Payments received', 'By method and day', 'wallet'],
            'aged-debtors' => ['Aged debtors', 'Current, 30, 60, 90, 120+ days', 'clock'],
            'aged-creditors' => ['Aged creditors', 'What we owe suppliers, by age', 'truck'],
            'expenses'     => ['Expenses by category', 'Where the money goes', 'receipt'],
            'inventory'    => ['Stock valuation', 'Stock on hand at cost & retail', 'box'],
        ],
    ];

    public function index()
    {
        return view('reports/index', ['catalogue' => self::CATALOGUE]);
    }

    public function show(string $slug)
    {
        $def = $this->build($slug);
        return view('reports/show', $def + ['slug' => $slug]);
    }

    public function export(string $slug, string $format)
    {
        $def = $this->build($slug);
        return $this->exportTable($format, $def['title'], $def['columns'], $def['rows'], $def['meta'], $def['totals']);
    }

    public function sendWeekly()
    {
        $ok = WeeklyReport::send(true);
        return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Weekly performance report emailed.' : 'Could not send the weekly report. Check recipients and SMTP settings.');
    }

    // --------------------------------------------------------------------

    private function title(string $slug): string
    {
        foreach (self::CATALOGUE as $items) {
            if (isset($items[$slug])) {
                return $items[$slug][0];
            }
        }
        $this->notFound('Report');
        return '';
    }

    /**
     * Builds a report definition: title, meta, kpis, chart, columns, rows, totals.
     */
    private function build(string $slug): array
    {
        [$from, $to] = $this->range($slug === 'balance-sheet' ? 'year' : 'month');
        $title = $this->title($slug);
        $def = ['title' => $title, 'from' => $from, 'to' => $to, 'meta' => ['Period' => fdate($from) . ' – ' . fdate($to)], 'kpis' => [], 'chart' => null, 'columns' => [], 'rows' => [], 'totals' => [], 'dated' => true, 'note' => null];
        $money = static fn ($l) => ['label' => $l, 'type' => 'money'];
        $num = static fn ($l) => ['label' => $l, 'type' => 'number'];

        switch ($slug) {
            case 'sales':
                $s = R::summary($from, $to);
                $days = (strtotime($to) - strtotime($from)) / 86400;
                $series = R::series($from, $to, $days > 92 ? 'month' : ($days > 31 ? 'week' : 'day'));
                $def['kpis'] = [['Revenue (excl.)', money($s['revenue'])], ['Gross profit', money($s['gross_profit'])], ['Invoices', $s['invoices']], ['Average invoice', money($s['avg_invoice'])]];
                $def['chart'] = ['type' => 'bar', 'labels' => $series['labels'], 'datasets' => [['label' => 'Revenue', 'data' => $series['revenue']], ['label' => 'Collected', 'data' => $series['collected']]]];
                $rows = $this->db->table('invoices i')->select('i.number, i.issue_date, i.subtotal, i.discount_total, i.vat_total, i.total, i.cost_total, i.status, c.first_name, c.last_name, c.company_name, c.type')
                    ->join('customers c', 'c.id = i.customer_id')->where('i.deleted_at', null)->whereNotIn('i.status', ['draft', 'void'])
                    ->where('i.issue_date >=', $from)->where('i.issue_date <=', $to)->orderBy('i.issue_date')->get()->getResultArray();
                $def['rows'] = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'net' => $r['subtotal'] - $r['discount_total'], 'gp' => $r['subtotal'] - $r['discount_total'] - $r['cost_total']], $rows);
                $def['columns'] = ['number' => ['label' => 'Invoice'], 'issue_date' => ['label' => 'Date'], 'customer' => ['label' => 'Customer'], 'status' => ['label' => 'Status'], 'net' => $money('Excl. VAT'), 'vat_total' => $money('VAT'), 'total' => $money('Total'), 'gp' => $money('Gross profit')];
                $def['totals'] = ['number' => 'Totals', 'net' => $s['revenue'], 'vat_total' => $s['vat'], 'total' => $s['gross'], 'gp' => $s['gross_profit']];
                break;

            case 'revenue-mix':
                $rows = R::revenueByType($from, $to);
                $sum = array_sum(array_column($rows, 'value')) ?: 1;
                $def['chart'] = ['type' => 'doughnut', 'labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Revenue', 'data' => array_column($rows, 'value')]]];
                $def['rows'] = array_map(static fn ($r) => $r + ['share' => round($r['value'] / $sum * 100, 1)], $rows);
                $def['columns'] = ['label' => ['label' => 'Type'], 'value' => $money('Revenue (excl.)'), 'share' => $num('Share %')];
                $def['totals'] = ['label' => 'Total', 'value' => array_sum(array_column($rows, 'value'))];
                break;

            case 'top-services':
                $rows = R::topServices($from, $to, 50);
                $def['chart'] = ['type' => 'bar', 'horizontal' => true, 'labels' => array_map(static fn ($r) => mb_strimwidth($r['description'], 0, 30, '…'), array_slice($rows, 0, 10)), 'datasets' => [['label' => 'Revenue', 'data' => array_map(static fn ($r) => round((float) $r['amount'], 2), array_slice($rows, 0, 10))]]];
                $def['rows'] = $rows;
                $def['columns'] = ['description' => ['label' => 'Item'], 'item_type' => ['label' => 'Type'], 'qty' => $num('Qty / hrs'), 'amount' => $money('Revenue (excl.)')];
                $def['totals'] = ['description' => 'Total', 'amount' => array_sum(array_column($rows, 'amount'))];
                break;

            case 'customers':
                $rows = R::topCustomers($from, $to, 100);
                $def['chart'] = ['type' => 'bar', 'horizontal' => true, 'labels' => array_column(array_slice($rows, 0, 10), 'name'), 'datasets' => [['label' => 'Spend', 'data' => array_map('floatval', array_column(array_slice($rows, 0, 10), 'amount'))]]];
                $def['rows'] = $rows;
                $def['columns'] = ['name' => ['label' => 'Customer'], 'invoices' => $num('Invoices'), 'amount' => $money('Total spend (incl.)')];
                $def['totals'] = ['name' => 'Total', 'amount' => array_sum(array_column($rows, 'amount'))];
                break;

            case 'jobs':
                $rows = $this->db->table('job_cards j')->select('j.number, j.created_at, j.status, j.job_type, j.total, j.completed_at, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
                    ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')->where('j.deleted_at', null)
                    ->where('j.created_at >=', $from . ' 00:00:00')->where('j.created_at <=', $to . ' 23:59:59')->orderBy('j.id')->get()->getResultArray();
                $byStatus = [];
                foreach ($rows as $r) {
                    $byStatus[job_statuses()[$r['status']]['label'] ?? $r['status']] = ($byStatus[job_statuses()[$r['status']]['label'] ?? $r['status']] ?? 0) + 1;
                }
                $turn = array_filter(array_map(static fn ($r) => $r['completed_at'] ? (strtotime($r['completed_at']) - strtotime($r['created_at'])) / 3600 : null, $rows));
                $def['kpis'] = [['Jobs opened', count($rows)], ['Completed', count($turn)], ['Avg turnaround', $turn ? round(array_sum($turn) / count($turn), 1) . ' h' : '—'], ['Job value', money(array_sum(array_column($rows, 'total')))]];
                $def['chart'] = ['type' => 'pie', 'labels' => array_keys($byStatus), 'datasets' => [['label' => 'Jobs', 'data' => array_values($byStatus)]], 'count' => true];
                $def['rows'] = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'vehicle' => trim($r['make'] . ' ' . $r['model']), 'status_label' => job_statuses()[$r['status']]['label'] ?? $r['status']], $rows);
                $def['columns'] = ['number' => ['label' => 'Job'], 'created_at' => ['label' => 'Opened'], 'customer' => ['label' => 'Customer'], 'registration' => ['label' => 'Reg.'], 'vehicle' => ['label' => 'Vehicle'], 'job_type' => ['label' => 'Type'], 'status_label' => ['label' => 'Status'], 'total' => $money('Value')];
                $def['totals'] = ['number' => 'Total', 'total' => array_sum(array_column($rows, 'total'))];
                break;

            case 'technicians':
                $rows = R::mechanicProductivity($from, $to);
                $def['chart'] = ['type' => 'bar', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => 'Hours worked', 'data' => array_column($rows, 'hours_worked'), 'money' => false], ['label' => 'Hours sold', 'data' => array_column($rows, 'hours_sold'), 'money' => false]], 'count' => true];
                $def['rows'] = $rows;
                $def['columns'] = ['name' => ['label' => 'Technician'], 'jobs' => $num('Jobs done'), 'hours_worked' => $num('Hours clocked'), 'hours_sold' => $num('Hours sold'), 'efficiency' => $num('Efficiency %'), 'revenue' => $money('Revenue')];
                $def['totals'] = ['name' => 'Total', 'revenue' => array_sum(array_column($rows, 'revenue'))];
                $def['note'] = 'Efficiency = labour hours invoiced ÷ hours clocked on job cards. Above 100% means jobs are beating book time.';
                break;

            case 'profit-loss':
                $tb = $this->ledgerBalances($from, $to, ['income', 'expense']);
                $income = array_values(array_filter($tb, static fn ($a) => $a['type'] === 'income'));
                $cos = array_values(array_filter($tb, static fn ($a) => $a['type'] === 'expense' && $a['sub_type'] === 'cost_of_sales'));
                $opex = array_values(array_filter($tb, static fn ($a) => $a['type'] === 'expense' && $a['sub_type'] !== 'cost_of_sales'));
                $sum = static fn ($rows) => array_sum(array_column($rows, 'amount'));
                $gp = $sum($income) - $sum($cos);
                $np = $gp - $sum($opex);
                $rows = [['section' => 'INCOME', 'code' => '', 'name' => '', 'amount' => null]];
                foreach ($income as $a) { $rows[] = ['section' => '', 'code' => $a['code'], 'name' => $a['name'], 'amount' => $a['amount']]; }
                $rows[] = ['section' => 'Total income', 'code' => '', 'name' => '', 'amount' => $sum($income)];
                $rows[] = ['section' => 'COST OF SALES', 'code' => '', 'name' => '', 'amount' => null];
                foreach ($cos as $a) { $rows[] = ['section' => '', 'code' => $a['code'], 'name' => $a['name'], 'amount' => $a['amount']]; }
                $rows[] = ['section' => 'GROSS PROFIT', 'code' => '', 'name' => '', 'amount' => $gp];
                $rows[] = ['section' => 'OPERATING EXPENSES', 'code' => '', 'name' => '', 'amount' => null];
                foreach ($opex as $a) { $rows[] = ['section' => '', 'code' => $a['code'], 'name' => $a['name'], 'amount' => $a['amount']]; }
                $rows[] = ['section' => 'Total operating expenses', 'code' => '', 'name' => '', 'amount' => $sum($opex)];
                $def['kpis'] = [['Income', money($sum($income))], ['Gross profit', money($gp)], ['Expenses', money($sum($opex))], ['Net profit', money($np)]];
                $def['chart'] = ['type' => 'doughnut', 'labels' => array_column($opex, 'name'), 'datasets' => [['label' => 'Expenses', 'data' => array_column($opex, 'amount')]]];
                $def['rows'] = $rows;
                $def['columns'] = ['section' => ['label' => 'Section'], 'code' => ['label' => 'Account'], 'name' => ['label' => 'Description'], 'amount' => $money('Amount')];
                $def['totals'] = ['section' => 'NET PROFIT / (LOSS)', 'amount' => $np];
                $def['statement'] = true;
                break;

            case 'balance-sheet':
                $def['meta'] = ['As at' => fdate($to)];
                $all = $this->ledgerBalances(null, $to, ['asset', 'liability', 'equity', 'income', 'expense']);
                $earnings = 0.0;
                foreach ($all as $a) {
                    if ($a['type'] === 'income') { $earnings += $a['amount']; }
                    if ($a['type'] === 'expense') { $earnings -= $a['amount']; }
                }
                $rows = [];
                $tot = [];
                foreach (['asset' => 'ASSETS', 'liability' => 'LIABILITIES', 'equity' => 'EQUITY'] as $type => $label) {
                    $rows[] = ['section' => $label, 'code' => '', 'name' => '', 'amount' => null];
                    $tot[$type] = 0;
                    foreach ($all as $a) {
                        if ($a['type'] === $type && abs($a['amount']) > 0.004) {
                            $rows[] = ['section' => '', 'code' => $a['code'], 'name' => $a['name'], 'amount' => $a['amount']];
                            $tot[$type] += $a['amount'];
                        }
                    }
                    if ($type === 'equity') {
                        $rows[] = ['section' => '', 'code' => '', 'name' => 'Current earnings (unappropriated profit)', 'amount' => $earnings];
                        $tot[$type] += $earnings;
                    }
                    $rows[] = ['section' => 'Total ' . strtolower($label), 'code' => '', 'name' => '', 'amount' => $tot[$type]];
                }
                $def['kpis'] = [['Total assets', money($tot['asset'])], ['Total liabilities', money($tot['liability'])], ['Equity', money($tot['equity'])], ['Check (A − L − E)', money($tot['asset'] - $tot['liability'] - $tot['equity'])]];
                $def['chart'] = ['type' => 'doughnut', 'labels' => ['Liabilities', 'Equity'], 'datasets' => [['label' => 'Funding', 'data' => [round($tot['liability'], 2), round($tot['equity'], 2)]]]];
                $def['rows'] = $rows;
                $def['columns'] = ['section' => ['label' => 'Section'], 'code' => ['label' => 'Account'], 'name' => ['label' => 'Description'], 'amount' => $money('Amount')];
                $def['totals'] = ['section' => 'LIABILITIES + EQUITY', 'amount' => $tot['liability'] + $tot['equity']];
                $def['statement'] = true;
                $def['single_date'] = true;
                break;

            case 'trial-balance':
                $rows = $this->ledgerBalances($from, $to, ['asset', 'liability', 'equity', 'income', 'expense'], true);
                $rows = array_values(array_filter($rows, static fn ($r) => abs($r['debit']) > 0.004 || abs($r['credit']) > 0.004));
                $def['rows'] = $rows;
                $def['columns'] = ['code' => ['label' => 'Code'], 'name' => ['label' => 'Account'], 'type' => ['label' => 'Type'], 'debit' => $money('Debit'), 'credit' => $money('Credit')];
                $def['totals'] = ['code' => 'Totals', 'debit' => array_sum(array_column($rows, 'debit')), 'credit' => array_sum(array_column($rows, 'credit'))];
                break;

            case 'vat':
                $v = R::vat($from, $to);
                $def['kpis'] = [['Output VAT (sales)', money($v['output_vat'])], ['Input VAT (purchases)', money($v['input_vat'])], [$v['payable'] >= 0 ? 'VAT payable to SARS' : 'VAT refund due', money(abs($v['payable']))], ['VAT rate', setting('vat_rate', 15) . '%']];
                $def['chart'] = ['type' => 'bar', 'labels' => ['Output VAT', 'Input VAT', 'Net'], 'datasets' => [['label' => 'VAT', 'data' => [round($v['output_vat'], 2), round($v['input_vat'], 2), round($v['payable'], 2)]]]];
                $def['rows'] = [
                    ['field' => '1', 'desc' => 'Standard-rated supplies (excl. VAT)', 'base' => $v['output_base'], 'vat' => $v['output_vat']],
                    ['field' => '14', 'desc' => 'Capital & other goods/services acquired (input tax)', 'base' => $v['input_base'], 'vat' => $v['input_vat']],
                    ['field' => '20', 'desc' => $v['payable'] >= 0 ? 'VAT payable' : 'VAT refundable', 'base' => null, 'vat' => $v['payable']],
                ];
                $def['columns'] = ['field' => ['label' => 'VAT201 field'], 'desc' => ['label' => 'Description'], 'base' => $money('Value excl. VAT'), 'vat' => $money('VAT')];
                $def['note'] = 'Figures are a guide for completing your VAT201 on SARS eFiling. Ensure every input-VAT claim is supported by a valid tax invoice.';
                break;

            case 'payments':
                $byMethod = R::paymentsByMethod($from, $to);
                $def['chart'] = ['type' => 'pie', 'labels' => array_column($byMethod, 'label'), 'datasets' => [['label' => 'Collected', 'data' => array_column($byMethod, 'value')]]];
                $rows = $this->db->table('payments p')->select('p.number, p.paid_at, p.method, p.reference, p.amount, i.number invoice, c.first_name, c.last_name, c.company_name, c.type')
                    ->join('invoices i', 'i.id = p.invoice_id', 'left')->join('customers c', 'c.id = p.customer_id')->where('p.status', 'completed')
                    ->where('p.paid_at >=', $from . ' 00:00:00')->where('p.paid_at <=', $to . ' 23:59:59')->orderBy('p.paid_at')->get()->getResultArray();
                $def['kpis'] = array_map(static fn ($m) => [$m['label'], money($m['value'])], array_slice($byMethod, 0, 4));
                $def['rows'] = array_map(static fn ($r) => $r + ['customer' => customer_name($r), 'method' => strtoupper($r['method'])], $rows);
                $def['columns'] = ['number' => ['label' => 'Receipt'], 'paid_at' => ['label' => 'Date'], 'customer' => ['label' => 'Customer'], 'invoice' => ['label' => 'Invoice'], 'method' => ['label' => 'Method'], 'reference' => ['label' => 'Reference'], 'amount' => $money('Amount')];
                $def['totals'] = ['number' => 'Total', 'amount' => array_sum(array_column($rows, 'amount'))];
                break;

            case 'aged-debtors':
                $def['dated'] = false;
                $def['meta'] = ['As at' => fdate(date('Y-m-d'))];
                $rows = R::agedDebtors();
                $sum = static fn ($k) => array_sum(array_column($rows, $k));
                $def['kpis'] = [['Total owed', money($sum('total'))], ['Current', money($sum('current'))], ['30–60 days', money($sum('d30') + $sum('d60'))], ['90+ days', money($sum('d90') + $sum('d90plus'))]];
                $def['chart'] = ['type' => 'bar', 'labels' => ['Current', '1–30', '31–60', '61–90', '90+'], 'datasets' => [['label' => 'Outstanding', 'data' => [round($sum('current'), 2), round($sum('d30'), 2), round($sum('d60'), 2), round($sum('d90'), 2), round($sum('d90plus'), 2)]]]];
                $def['rows'] = $rows;
                $def['columns'] = ['name' => ['label' => 'Customer'], 'phone' => ['label' => 'Phone'], 'current' => $money('Current'), 'd30' => $money('1–30 days'), 'd60' => $money('31–60'), 'd90' => $money('61–90'), 'd90plus' => $money('90+'), 'total' => $money('Total')];
                $def['totals'] = ['name' => 'Totals', 'current' => $sum('current'), 'd30' => $sum('d30'), 'd60' => $sum('d60'), 'd90' => $sum('d90'), 'd90plus' => $sum('d90plus'), 'total' => $sum('total')];
                break;

            case 'kpi-benchmarks':
                $s = R::summary($from, $to);
                $days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
                $workDays = max(1, (int) round($days * 5.5 / 7));
                $mix = array_column(R::revenueByType($from, $to), 'value', 'label');
                $labourRev = (float) (($mix['Labour'] ?? 0) + ($mix['Service'] ?? 0));
                $partsRev = (float) (($mix['Part'] ?? 0) + ($mix['Fluid / Oil'] ?? 0) + ($mix['Tyre'] ?? 0));
                $tech = R::mechanicProductivity($from, $to);
                $worked = array_sum(array_column($tech, 'hours_worked'));
                $sold = array_sum(array_column($tech, 'hours_sold'));
                $techCount = max(1, count(array_filter($tech, static fn ($t) => $t['hours_worked'] > 0)));
                $jobsDone = max(1, $s['jobs_completed']);
                $comebacks = $this->db->table('job_cards')->where('deleted_at', null)->where('created_at >=', $from . ' 00:00:00')->where('created_at <=', $to . ' 23:59:59')
                    ->groupStart()->where('warranty_claim', 1)->orWhere('job_type', 'Warranty')->groupEnd()->countAllResults();
                $turn = $this->db->table('job_cards')->select('checked_in_at, completed_at')->where('deleted_at', null)->where('completed_at >=', $from . ' 00:00:00')->where('completed_at <=', $to . ' 23:59:59')->where('checked_in_at IS NOT NULL')->get()->getResultArray();
                $turnHrs = $turn ? array_sum(array_map(static fn ($t) => max(0, strtotime($t['completed_at']) - strtotime($t['checked_in_at'])) / 3600, $turn)) / count($turn) : 0;
                $bays = max(1, (int) setting('workshop_bays', 4));
                $partsCost = (float) $this->db->table('invoices')->selectSum('cost_total')->where('deleted_at', null)->whereNotIn('status', ['draft', 'void'])->where('issue_date >=', $from)->where('issue_date <=', $to)->get()->getRow()->cost_total;
                // [label, value, display, target text, good?]
                $k = [
                    ['Average repair order (incl. VAT)', $s['avg_invoice'], money($s['avg_invoice']), 'R3 500+', $s['avg_invoice'] >= 3500],
                    ['Car count per working day', $s['jobs_completed'] / $workDays, number_format($s['jobs_completed'] / $workDays, 1), '≥ 1 per bay (' . $bays . ')', $s['jobs_completed'] / $workDays >= $bays],
                    ['Labour efficiency (hours sold ÷ clocked)', $worked > 0 ? $sold / $worked * 100 : 0, $worked > 0 ? round($sold / $worked * 100) . '%' : 'no time data', '≥ 100%', $worked > 0 && $sold / $worked >= 1],
                    ['Hours sold per technician per day', $sold / $techCount / $workDays, number_format($sold / $techCount / $workDays, 1) . ' h', '≥ 6 h', $sold / $techCount / $workDays >= 6],
                    ['Bay utilisation (clocked ÷ available)', $worked / ($bays * 8 * $workDays) * 100, round($worked / ($bays * 8 * $workDays) * 100) . '%', '≥ 75%', $worked / ($bays * 8 * $workDays) >= .75],
                    ['Parts gross margin', $partsRev > 0 ? ($partsRev - $partsCost) / $partsRev * 100 : 0, $partsRev > 0 ? round(($partsRev - $partsCost) / $partsRev * 100) . '%' : '—', '35–45%', $partsRev > 0 && ($partsRev - $partsCost) / $partsRev >= .35],
                    ['Labour share of revenue', $s['revenue'] > 0 ? $labourRev / $s['revenue'] * 100 : 0, $s['revenue'] > 0 ? round($labourRev / $s['revenue'] * 100) . '%' : '—', '45–55%', $s['revenue'] > 0 && $labourRev / $s['revenue'] >= .45],
                    ['Overall gross margin', $s['gross_margin'], round($s['gross_margin']) . '%', '≥ 60%', $s['gross_margin'] >= 60],
                    ['Net profit margin', $s['revenue'] > 0 ? $s['net_profit'] / $s['revenue'] * 100 : 0, $s['revenue'] > 0 ? round($s['net_profit'] / $s['revenue'] * 100) . '%' : '—', '≥ 15%', $s['revenue'] > 0 && $s['net_profit'] / $s['revenue'] >= .15],
                    ['Comeback (warranty) rate', $comebacks / $jobsDone * 100, round($comebacks / $jobsDone * 100, 1) . '%', '< 2%', $comebacks / $jobsDone < .02],
                    ['Quote conversion', $s['quote_win_rate'], round($s['quote_win_rate']) . '%', '≥ 60%', $s['quote_win_rate'] >= 60],
                    ['Average turnaround (check-in → ready)', $turnHrs, $turnHrs ? round($turnHrs, 1) . ' h' : '—', '≤ 8 h', $turnHrs > 0 && $turnHrs <= 8],
                ];
                $good = count(array_filter($k, static fn ($x) => $x[4]));
                $def['kpis'] = [['Health score', round($good / count($k) * 100) . '%'], ['On target', $good . ' of ' . count($k)], ['Average repair order', money($s['avg_invoice'])], ['Labour efficiency', $worked > 0 ? round($sold / $worked * 100) . '%' : '—']];
                $def['rows'] = array_map(static fn ($x) => ['metric' => $x[0], 'value' => $x[2], 'target' => $x[3], 'status' => $x[4] ? 'On target' : 'Below target'], $k);
                $def['columns'] = ['metric' => ['label' => 'KPI'], 'value' => ['label' => 'Wheelsturn'], 'target' => ['label' => 'Benchmark'], 'status' => ['label' => 'Status']];
                $def['note'] = 'Benchmarks are guideline targets for a well-run independent workshop and should be adapted to your market. Labour efficiency and bay utilisation need technicians to clock on and off on job cards.';
                break;

            case 'commissions':
                $techs = $this->db->table('users')->select('id, first_name, last_name, commission_percent, monthly_target')->whereIn('role', ['mechanic', 'manager'])->where('deleted_at', null)->get()->getResultArray();
                $months = max(1, (int) round((strtotime($to) - strtotime($from)) / (86400 * 30.4)));
                $rows = [];
                foreach ($techs as $t) {
                    $lab = $this->db->table('invoices i')->select('COALESCE(SUM(li.line_subtotal - li.line_discount),0) amt, COALESCE(SUM(li.quantity),0) hrs', false)
                        ->join('line_items li', "li.document_type = 'invoice' AND li.document_id = i.id")->where('li.mechanic_id', $t['id'])->whereIn('li.item_type', ['labour', 'service'])
                        ->where('i.deleted_at', null)->whereNotIn('i.status', ['draft', 'void'])->where('i.issue_date >=', $from)->where('i.issue_date <=', $to)->get()->getRowArray();
                    $target = (float) $t['monthly_target'] * $months;
                    $rows[] = ['name' => trim($t['first_name'] . ' ' . $t['last_name']), 'hours' => round((float) $lab['hrs'], 1), 'labour' => round((float) $lab['amt'], 2),
                        'rate' => (float) $t['commission_percent'], 'commission' => round((float) $lab['amt'] * (float) $t['commission_percent'] / 100, 2),
                        'target' => $target, 'progress' => $target > 0 ? round((float) $lab['amt'] / $target * 100) : null];
                }
                usort($rows, static fn ($a, $b) => $b['labour'] <=> $a['labour']);
                $def['chart'] = ['type' => 'bar', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => 'Labour sold', 'data' => array_column($rows, 'labour')], ['label' => 'Target', 'data' => array_column($rows, 'target')]]];
                $def['rows'] = $rows;
                $def['columns'] = ['name' => ['label' => 'Technician'], 'hours' => $num('Hours sold'), 'labour' => $money('Labour sold'), 'rate' => $num('Commission %'), 'commission' => $money('Commission'), 'target' => $money('Target'), 'progress' => $num('Target %')];
                $def['totals'] = ['name' => 'Totals', 'labour' => array_sum(array_column($rows, 'labour')), 'commission' => array_sum(array_column($rows, 'commission'))];
                $def['note'] = 'Set each technician\'s commission % and monthly labour target in Team. Labour is credited to the technician selected on each labour line.';
                break;

            case 'job-profitability':
                $jobs = $this->db->table('job_cards j')->select('j.id, j.number, j.completed_at, j.subtotal, j.discount_total, v.registration, c.first_name, c.last_name, c.company_name, c.type')
                    ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')->where('j.deleted_at', null)->whereIn('j.status', ['completed', 'invoiced', 'collected'])
                    ->where('j.completed_at >=', $from . ' 00:00:00')->where('j.completed_at <=', $to . ' 23:59:59')->orderBy('j.completed_at')->get()->getResultArray();
                $rows = [];
                foreach ($jobs as $j) {
                    $c = \App\Libraries\JobCosting::for((int) $j['id']);
                    $rows[] = ['number' => $j['number'], 'date' => substr((string) $j['completed_at'], 0, 10), 'customer' => customer_name($j), 'registration' => $j['registration']] + $c;
                }
                $rev = array_sum(array_column($rows, 'revenue'));
                $profit = array_sum(array_column($rows, 'profit'));
                $def['kpis'] = [['Jobs', count($rows)], ['Revenue', money($rev)], ['Gross profit', money($profit)], ['Avg margin', $rev > 0 ? round($profit / $rev * 100, 1) . '%' : '—']];
                $bands = ['< 20%' => 0, '20–40%' => 0, '40–60%' => 0, '60%+' => 0];
                foreach ($rows as $r) {
                    $bands[$r['margin'] < 20 ? '< 20%' : ($r['margin'] < 40 ? '20–40%' : ($r['margin'] < 60 ? '40–60%' : '60%+'))]++;
                }
                $def['chart'] = ['type' => 'doughnut', 'labels' => array_keys($bands), 'datasets' => [['label' => 'Jobs', 'data' => array_values($bands)]], 'count' => true];
                $def['rows'] = $rows;
                $def['columns'] = ['number' => ['label' => 'Job'], 'date' => ['label' => 'Completed'], 'customer' => ['label' => 'Customer'], 'registration' => ['label' => 'Reg.'], 'revenue' => $money('Revenue'), 'parts_cost' => $money('Parts cost'), 'labour_cost' => $money('Labour cost'), 'profit' => $money('Profit'), 'margin' => $num('Margin %')];
                $def['totals'] = ['number' => 'Totals', 'revenue' => $rev, 'parts_cost' => array_sum(array_column($rows, 'parts_cost')), 'labour_cost' => array_sum(array_column($rows, 'labour_cost')), 'profit' => $profit];
                $def['note'] = 'Labour cost = hours clocked on the job × each technician\'s cost rate (Team → cost rate).';
                break;

            case 'warranties':
                $def['dated'] = false;
                $def['meta'] = ['As at' => fdate(date('Y-m-d'))];
                $items = $this->db->table('line_items li')->select('li.description, li.item_type, li.warranty_months, i.number, i.issue_date, i.id invoice_id, c.first_name, c.last_name, c.company_name, c.type, v.registration')
                    ->join('invoices i', "i.id = li.document_id AND li.document_type = 'invoice'")->join('customers c', 'c.id = i.customer_id')->join('vehicles v', 'v.id = i.vehicle_id', 'left')
                    ->where('li.warranty_months >', 0)->where('i.deleted_at', null)->whereNotIn('i.status', ['draft', 'void'])->orderBy('i.issue_date', 'DESC')->get()->getResultArray();
                $rows = [];
                foreach ($items as $it) {
                    $expires = date('Y-m-d', strtotime($it['issue_date'] . ' +' . (int) $it['warranty_months'] . ' months'));
                    if ($expires >= date('Y-m-d')) {
                        $rows[] = ['registration' => $it['registration'], 'customer' => customer_name($it), 'description' => $it['description'], 'invoice' => $it['number'], 'issued' => $it['issue_date'], 'months' => (int) $it['warranty_months'], 'expires' => $expires];
                    }
                }
                $claims = $this->db->table('job_cards')->where('deleted_at', null)->groupStart()->where('warranty_claim', 1)->orWhere('job_type', 'Warranty')->groupEnd()->countAllResults();
                $def['kpis'] = [['Items under warranty', count($rows)], ['Expiring in 30 days', count(array_filter($rows, static fn ($r) => $r['expires'] <= date('Y-m-d', strtotime('+30 days'))))], ['Warranty jobs (comebacks)', $claims]];
                $def['rows'] = $rows;
                $def['columns'] = ['registration' => ['label' => 'Reg.'], 'customer' => ['label' => 'Customer'], 'description' => ['label' => 'Part / labour'], 'invoice' => ['label' => 'Invoice'], 'issued' => ['label' => 'Fitted'], 'months' => $num('Months'), 'expires' => ['label' => 'Warranty ends']];
                $def['note'] = 'Set warranty months on items in Parts & Inventory. Mark comebacks as job type "Warranty" to track them.';
                break;

            case 'aged-creditors':
                $def['dated'] = false;
                $def['meta'] = ['As at' => fdate(date('Y-m-d'))];
                $rows = \App\Libraries\Purchasing::agedCreditors();
                $sum = static fn ($k) => array_sum(array_column($rows, $k));
                $def['kpis'] = [['Total owed to suppliers', money($sum('total'))], ['Not yet due', money($sum('current'))], ['1–60 days late', money($sum('d30') + $sum('d60'))], ['60+ days late', money($sum('d90') + $sum('d90plus'))]];
                $def['chart'] = ['type' => 'bar', 'labels' => ['Current', '1–30', '31–60', '61–90', '90+'], 'datasets' => [['label' => 'Owing', 'data' => [round($sum('current'), 2), round($sum('d30'), 2), round($sum('d60'), 2), round($sum('d90'), 2), round($sum('d90plus'), 2)]]]];
                $def['rows'] = $rows;
                $def['columns'] = ['name' => ['label' => 'Supplier'], 'phone' => ['label' => 'Phone'], 'current' => $money('Current'), 'd30' => $money('1–30 days'), 'd60' => $money('31–60'), 'd90' => $money('61–90'), 'd90plus' => $money('90+'), 'total' => $money('Total')];
                $def['totals'] = ['name' => 'Totals', 'current' => $sum('current'), 'd30' => $sum('d30'), 'd60' => $sum('d60'), 'd90' => $sum('d90'), 'd90plus' => $sum('d90plus'), 'total' => $sum('total')];
                break;

            case 'cash-flow':
                $cf = $this->cashFlow($from, $to);
                $def['kpis'] = [['Opening cash', money($cf['opening'])], ['Net operating cash', money($cf['totals']['Operating'])], ['Net change in cash', money($cf['net'])], ['Closing cash', money($cf['closing'])]];
                $def['chart'] = ['type' => 'bar', 'labels' => array_keys($cf['totals']), 'datasets' => [['label' => 'Net cash', 'data' => array_map(static fn ($v) => round($v, 2), array_values($cf['totals']))]]];
                $rows = [['section' => 'Opening cash & bank balance', 'name' => '', 'amount' => $cf['opening']]];
                foreach ($cf['groups'] as $section => $items) {
                    $rows[] = ['section' => strtoupper($section) . ' ACTIVITIES', 'name' => '', 'amount' => null];
                    foreach ($items as $name => $amt) {
                        $rows[] = ['section' => '', 'name' => $name, 'amount' => $amt];
                    }
                    $rows[] = ['section' => 'Net cash from ' . strtolower($section) . ' activities', 'name' => '', 'amount' => $cf['totals'][$section]];
                }
                $def['rows'] = $rows;
                $def['columns'] = ['section' => ['label' => 'Section'], 'name' => ['label' => 'Line'], 'amount' => $money('Amount')];
                $def['totals'] = ['section' => 'CLOSING CASH & BANK BALANCE', 'amount' => $cf['closing']];
                $def['statement'] = true;
                $def['note'] = 'Direct method, built from every movement in your bank and cash accounts.';
                break;

            case 'general-ledger':
                $rows = $this->db->table('journal_lines l')->select('a.code, a.name account, e.entry_date, e.number, COALESCE(l.description, e.description) description, l.debit, l.credit')
                    ->join('journal_entries e', 'e.id = l.journal_entry_id')->join('accounts a', 'a.id = l.account_id')
                    ->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)->orderBy('a.code')->orderBy('e.entry_date')->orderBy('e.id')->get()->getResultArray();
                $def['rows'] = $rows;
                $def['columns'] = ['code' => ['label' => 'Code'], 'account' => ['label' => 'Account'], 'entry_date' => ['label' => 'Date'], 'number' => ['label' => 'Journal'], 'description' => ['label' => 'Description'], 'debit' => $money('Debit'), 'credit' => $money('Credit')];
                $def['totals'] = ['code' => 'Totals', 'debit' => array_sum(array_column($rows, 'debit')), 'credit' => array_sum(array_column($rows, 'credit'))];
                $def['note'] = 'Tip: open any account in Accounting → Chart of accounts for a running-balance view.';
                break;

            case 'expenses':
                $rows = R::expensesByAccount($from, $to);
                $def['chart'] = ['type' => 'doughnut', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => 'Spend', 'data' => array_map('floatval', array_column($rows, 'excl'))]]];
                $def['rows'] = $rows;
                $def['columns'] = ['code' => ['label' => 'Account'], 'name' => ['label' => 'Category'], 'n' => $num('Count'), 'excl' => $money('Excl. VAT'), 'vat' => $money('VAT'), 'total' => $money('Total')];
                $def['totals'] = ['code' => 'Totals', 'excl' => array_sum(array_column($rows, 'excl')), 'vat' => array_sum(array_column($rows, 'vat')), 'total' => array_sum(array_column($rows, 'total'))];
                break;

            case 'inventory':
                $def['dated'] = false;
                $def['meta'] = ['As at' => fdate(date('Y-m-d'))];
                $rows = $this->db->table('products')->select('sku, name, category, qty_on_hand, cost_price, sell_price, reorder_level')->where(['deleted_at' => null, 'track_stock' => 1])->orderBy('category')->orderBy('name')->get()->getResultArray();
                $rows = array_map(static fn ($r) => $r + ['cost_value' => (float) $r['qty_on_hand'] * (float) $r['cost_price'], 'retail_value' => (float) $r['qty_on_hand'] * (float) $r['sell_price']], $rows);
                $byCat = [];
                foreach ($rows as $r) {
                    $byCat[$r['category'] ?: 'Uncategorised'] = ($byCat[$r['category'] ?: 'Uncategorised'] ?? 0) + $r['cost_value'];
                }
                $def['chart'] = ['type' => 'pie', 'labels' => array_keys($byCat), 'datasets' => [['label' => 'Stock value', 'data' => array_map(static fn ($v) => round($v, 2), array_values($byCat))]]];
                $def['rows'] = $rows;
                $def['columns'] = ['sku' => ['label' => 'SKU'], 'name' => ['label' => 'Item'], 'category' => ['label' => 'Category'], 'qty_on_hand' => $num('On hand'), 'cost_price' => $money('Unit cost'), 'cost_value' => $money('Value at cost'), 'retail_value' => $money('Value at retail')];
                $def['totals'] = ['sku' => 'Totals', 'cost_value' => array_sum(array_column($rows, 'cost_value')), 'retail_value' => array_sum(array_column($rows, 'retail_value'))];
                break;
        }
        return $def;
    }

    /** Direct-method cash flow from all bank & cash account movements. */
    private function cashFlow(string $from, string $to): array
    {
        $bankIds = array_map('intval', array_column($this->db->table('accounts')->select('id')->whereIn('sub_type', ['bank', 'cash'])->get()->getResultArray(), 'id')) ?: [0];
        $opening = 0.0;
        foreach ($bankIds as $id) {
            $opening += \App\Libraries\Ledger::balance($id, date('Y-m-d', strtotime($from . ' -1 day')));
        }
        // Net bank movement per journal entry (excluding transfers between bank accounts)
        $moves = $this->db->table('journal_lines l')->select('l.journal_entry_id, e.source_type, SUM(l.debit - l.credit) net', false)
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->whereIn('l.account_id', $bankIds)
            ->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)->groupBy('l.journal_entry_id, e.source_type')->get()->getResultArray();
        $groups = ['Operating' => [], 'Investing' => [], 'Financing' => []];
        $label = [
            'payment' => 'Receipts from customers', 'supplier_payment' => 'Payments to suppliers', 'expense' => 'Operating expenses paid',
            'vat_payment' => 'VAT paid to / refunded by SARS', 'payfast_fee' => 'Payment gateway fees',
        ];
        foreach ($moves as $m) {
            $net = round((float) $m['net'], 2);
            if ($net == 0.0) {
                continue;
            }
            $section = 'Operating';
            $name = $label[$m['source_type']] ?? null;
            if ($name === null) {
                // Classify manual / bank / opening entries by the other side of the journal
                $other = $this->db->table('journal_lines l')->select('a.type, a.sub_type, a.name')->join('accounts a', 'a.id = l.account_id')
                    ->where('l.journal_entry_id', $m['journal_entry_id'])->whereNotIn('l.account_id', $bankIds)->orderBy('l.id')->get()->getRowArray();
                $name = $other['name'] ?? 'Other';
                if (in_array($other['sub_type'] ?? '', ['fixed_asset', 'contra_asset'], true)) {
                    $section = 'Investing';
                } elseif (($other['type'] ?? '') === 'equity' || ($other['sub_type'] ?? '') === 'non_current') {
                    $section = 'Financing';
                }
            }
            $groups[$section][$name] = ($groups[$section][$name] ?? 0) + $net;
        }
        $totals = array_map(static fn ($g) => array_sum($g), $groups);
        $net = array_sum($totals);
        return ['opening' => $opening, 'groups' => $groups, 'totals' => $totals, 'net' => $net, 'closing' => $opening + $net];
    }

    /**
     * Ledger account balances. Assets/expenses are debit-normal, others credit-normal.
     * $from = null means "since inception".
     */
    private function ledgerBalances(?string $from, string $to, array $types, bool $split = false): array
    {
        $b = $this->db->table('accounts a')
            ->select('a.id, a.code, a.name, a.type, a.sub_type, COALESCE(SUM(l.debit),0) dr, COALESCE(SUM(l.credit),0) cr')
            ->join('journal_lines l', 'l.account_id = a.id', 'left')
            ->join('journal_entries e', 'e.id = l.journal_entry_id', 'left')
            ->whereIn('a.type', $types)
            ->groupStart()->where('e.id', null)->orGroupStart()->where('e.entry_date <=', $to);
        if ($from) {
            $b->where('e.entry_date >=', $from);
        }
        $b->groupEnd()->groupEnd();
        $rows = $b->groupBy('a.id, a.code, a.name, a.type, a.sub_type')->orderBy('a.code')->get()->getResultArray();
        return array_map(static function ($r) use ($split) {
            $dr = (float) $r['dr'];
            $cr = (float) $r['cr'];
            $debitNormal = in_array($r['type'], ['asset', 'expense'], true);
            $r['amount'] = round($debitNormal ? $dr - $cr : $cr - $dr, 2);
            if ($split) {
                $net = $dr - $cr;
                $r['debit'] = $net > 0 ? round($net, 2) : 0;
                $r['credit'] = $net < 0 ? round(-$net, 2) : 0;
            }
            return $r;
        }, $rows);
    }
}
