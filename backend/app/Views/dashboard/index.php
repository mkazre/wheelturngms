<?php

use App\Controllers\Dashboard;
use App\Libraries\Reports;

$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-sm font-medium text-crimson-700 dark:text-crimson-400"><?= date('l, d F Y') ?></p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl"><?= $greet ?>, <?= esc(auth_user('first_name')) ?></h1>
        <p class="mt-1 text-sm text-slate-500">Here's how Wheelsturn is performing · <?= fdate($from) ?> – <?= fdate($to) ?></p>
    </div>
    <div class="flex flex-wrap gap-1 rounded-2xl bg-white p-1 shadow-soft ring-1 ring-slate-200/70 dark:bg-navy-900 dark:ring-white/10">
        <?php foreach (Dashboard::PERIODS as $k => $label): ?>
            <a href="?period=<?= $k ?>" class="rounded-xl px-3 py-1.5 text-xs font-semibold transition <?= $period === $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($target > 0 || $pendingBookings || $myTasks): ?>
<div class="mb-4 grid gap-4 lg:grid-cols-3">
    <?php if ($target > 0): $pct = min(100, round($mtdRevenue / $target * 100)); $dayPct = round((int) date('j') / (int) date('t') * 100); ?>
    <div class="card p-5 lg:col-span-2">
        <div class="flex items-end justify-between"><div><div class="kpi-label">Monthly target</div><div class="mt-1 text-2xl font-bold"><?= money($mtdRevenue) ?> <span class="text-sm font-medium text-slate-500">of <?= money($target) ?></span></div></div>
            <span class="<?= $pct >= $dayPct ? 'badge-green' : 'badge-amber' ?>"><?= $pct >= $dayPct ? 'On track' : 'Behind pace' ?> · <?= $pct ?>%</span></div>
        <div class="relative mt-3 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
            <div class="h-full rounded-full bg-gradient-to-r from-crimson-700 to-brand-red" style="width: <?= $pct ?>%"></div>
            <div class="absolute inset-y-0 w-0.5 bg-navy-900 dark:bg-white" style="left: <?= $dayPct ?>%" title="Where you should be today"></div>
        </div>
        <div class="mt-1 text-xs text-slate-500"><?= money(max(0, $target - $mtdRevenue)) ?> to go · <?= (int) date('t') - (int) date('j') ?> days left · need <?= money(max(0, $target - $mtdRevenue) / max(1, (int) date('t') - (int) date('j') + 1)) ?>/day</div>
    </div>
    <?php endif; ?>
    <div class="card p-5 <?= $target > 0 ? '' : 'lg:col-span-3' ?>">
        <div class="flex items-center justify-between"><div class="kpi-label">My tasks</div><a href="<?= site_url('tasks') ?>" class="text-xs font-semibold text-crimson-700 dark:text-crimson-400">All →</a></div>
        <?php if ($pendingBookings): ?><a href="<?= site_url('diary') ?>" class="mt-2 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"><?= icon('calendar', 'h-4 w-4') ?> <?= $pendingBookings ?> online booking request<?= $pendingBookings > 1 ? 's' : '' ?> to confirm</a><?php endif; ?>
        <ul class="mt-2 space-y-1.5 text-sm"><?php foreach ($myTasks as $t): ?><li class="flex justify-between gap-2"><span class="truncate"><?= esc($t['title']) ?></span><?php if ($t['due_at']): ?><span class="shrink-0 text-xs <?= strtotime($t['due_at']) < time() ? 'font-semibold text-red-600' : 'text-slate-400' ?>"><?= date('d M H:i', strtotime($t['due_at'])) ?></span><?php endif; ?></li><?php endforeach; ?>
            <?php if (!$myTasks): ?><li class="text-slate-500">No open tasks.</li><?php endif; ?></ul>
    </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Revenue (excl. VAT)', 'value' => money($now['revenue']), 'delta' => Reports::delta($now['revenue'], $prev['revenue']), 'icon' => 'chart', 'tone' => 'crimson', 'hint' => 'vs previous period']) ?>
    <?= view('partials/kpi', ['label' => 'Cash collected', 'value' => money($now['collected']), 'delta' => Reports::delta($now['collected'], $prev['collected']), 'icon' => 'wallet', 'tone' => 'green', 'hint' => $now['payments'] . ' payments']) ?>
    <?= view('partials/kpi', ['label' => 'Net profit', 'value' => money($now['net_profit']), 'delta' => Reports::delta($now['net_profit'], $prev['net_profit']), 'icon' => 'zap', 'tone' => 'violet', 'hint' => number_format($now['gross_margin'], 1) . '% gross margin']) ?>
    <?= view('partials/kpi', ['label' => 'Outstanding debtors', 'value' => money($outstanding), 'icon' => 'alert', 'tone' => 'amber', 'hint' => count($overdue) . ' overdue invoices']) ?>
</div>
<div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Vehicles in workshop', 'value' => $inWorkshop, 'icon' => 'car', 'tone' => 'navy', 'hint' => 'right now']) ?>
    <?= view('partials/kpi', ['label' => 'Jobs completed', 'value' => $now['jobs_completed'], 'delta' => Reports::delta($now['jobs_completed'], $prev['jobs_completed']), 'icon' => 'check', 'tone' => 'sky']) ?>
    <?= view('partials/kpi', ['label' => 'Average invoice', 'value' => money($now['avg_invoice']), 'delta' => Reports::delta($now['avg_invoice'], $prev['avg_invoice']), 'icon' => 'invoice', 'tone' => 'crimson']) ?>
    <?= view('partials/kpi', ['label' => 'Quote win rate', 'value' => number_format($now['quote_win_rate'], 0) . '%', 'icon' => 'quote', 'tone' => 'green', 'hint' => $now['quotes'] . ' quotes · ' . $now['new_customers'] . ' new customers']) ?>
</div>

<!-- Charts row 1 -->
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header">
            <div><div class="card-title">Revenue, collections &amp; expenses</div><p class="text-xs text-slate-500">Excl. VAT</p></div>
            <a href="<?= site_url('reports/sales?from=' . $from . '&to=' . $to) ?>" class="btn-ghost btn-sm">Full report →</a>
        </div>
        <div class="card-body"><div class="h-72"><canvas id="trendChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Revenue mix</div><span class="text-xs text-slate-500">by line type</span></div>
        <div class="card-body">
            <?php if ($byType): ?><div class="h-72"><canvas id="mixChart"></canvas></div>
            <?php else: ?><?= view('partials/empty', ['icon' => 'pie', 'title' => 'No sales yet', 'text' => 'Revenue mix appears once invoices are issued.']) ?><?php endif; ?>
        </div>
    </div>
</div>

<!-- Charts row 2 -->
<div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
    <div class="card">
        <div class="card-header"><div class="card-title">Workshop pipeline</div><a href="<?= site_url('workshop') ?>" class="btn-ghost btn-sm">Board →</a></div>
        <div class="card-body"><div class="h-64"><canvas id="statusChart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Payments by method</div></div>
        <div class="card-body">
            <?php if ($byMethod): ?><div class="h-64"><canvas id="methodChart"></canvas></div>
            <?php else: ?><?= view('partials/empty', ['icon' => 'card', 'title' => 'No payments yet']) ?><?php endif; ?>
        </div>
    </div>
    <div class="card lg:col-span-2 xl:col-span-1">
        <div class="card-header"><div class="card-title">Technician leaderboard</div><a href="<?= site_url('reports/technicians') ?>" class="btn-ghost btn-sm">Details →</a></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php $maxRev = max(1, ...array_column($mechanics ?: [['revenue' => 1]], 'revenue')); ?>
            <?php foreach ($mechanics as $i => $m): ?>
                <li class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full <?= $i === 0 ? 'bg-amber-400 text-amber-950' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' ?> text-xs font-bold"><?= $i + 1 ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between text-sm"><span class="truncate font-semibold"><?= esc($m['name']) ?></span><span class="font-semibold"><?= money_short($m['revenue']) ?></span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-crimson-700 to-brand-red" style="width: <?= round($m['revenue'] / $maxRev * 100) ?>%"></div></div>
                            <div class="mt-1 text-[11px] text-slate-500"><?= $m['jobs'] ?> jobs · <?= $m['hours_sold'] ?>h sold · <?= $m['efficiency'] ?>% efficiency</div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$mechanics): ?><li><?= view('partials/empty', ['icon' => 'users', 'title' => 'No technicians yet']) ?></li><?php endif; ?>
        </ul>
    </div>
</div>

<!-- Lists -->
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card">
        <div class="card-header"><div class="card-title">Today's bookings</div><a href="<?= site_url('job-cards/new') ?>" class="btn-ghost btn-sm">+ Book</a></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($todayJobs as $j): ?>
                <li><a href="<?= site_url('job-cards/' . $j['id']) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-white/5">
                    <div class="w-12 text-center text-xs font-bold text-crimson-700 dark:text-crimson-400"><?= date('H:i', strtotime($j['booked_for'])) ?></div>
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold"><?= esc($j['make'] . ' ' . $j['model']) ?> <span class="font-mono text-xs text-slate-500"><?= esc($j['registration']) ?></span></div><div class="truncate text-xs text-slate-500"><?= esc(customer_name($j)) ?></div></div>
                    <?= status_badge($j['status']) ?>
                </a></li>
            <?php endforeach; ?>
            <?php if (!$todayJobs): ?><li><?= view('partials/empty', ['icon' => 'calendar', 'title' => 'No bookings today']) ?></li><?php endif; ?>
        </ul>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Overdue invoices</div><a href="<?= site_url('reports/aged-debtors') ?>" class="btn-ghost btn-sm">Aged debtors →</a></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($overdue as $o): $days = (int) floor((time() - strtotime($o['due_date'])) / 86400); ?>
                <li><a href="<?= site_url('invoices/' . $o['id']) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-white/5">
                    <div class="min-w-0 flex-1"><div class="text-sm font-semibold"><?= esc($o['number']) ?></div><div class="truncate text-xs text-slate-500"><?= esc(customer_name($o)) ?></div></div>
                    <div class="text-right"><div class="text-sm font-bold"><?= money($o['balance_due']) ?></div><div class="text-[11px] font-semibold text-red-600"><?= $days ?> days late</div></div>
                </a></li>
            <?php endforeach; ?>
            <?php if (!$overdue): ?><li><?= view('partials/empty', ['icon' => 'check', 'title' => 'All caught up', 'text' => 'No overdue invoices.']) ?></li><?php endif; ?>
        </ul>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Top services &amp; parts</div></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($topServices as $s): ?>
                <li class="flex items-center gap-3 px-5 py-3">
                    <span class="<?= $s['item_type'] === 'labour' ? 'badge-navy' : 'badge-purple' ?>"><?= esc(ucfirst($s['item_type'])) ?></span>
                    <div class="min-w-0 flex-1 truncate text-sm"><?= esc($s['description']) ?></div>
                    <div class="text-sm font-semibold"><?= money_short($s['amount']) ?></div>
                </li>
            <?php endforeach; ?>
            <?php if (!$topServices): ?><li><?= view('partials/empty', ['icon' => 'wrench', 'title' => 'No data yet']) ?></li><?php endif; ?>
        </ul>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header"><div class="card-title">Recent activity</div></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($recent as $a):
                $url = match ($a['entity_type']) { 'job_card' => 'job-cards/', 'invoice' => 'invoices/', 'quote' => 'quotes/', 'customer' => 'customers/', default => null }; ?>
                <li class="flex items-start gap-3 px-5 py-3">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-crimson-600"></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-semibold"><?= esc(trim(($a['first_name'] ?? 'System') . ' ' . ($a['last_name'] ?? ''))) ?></span>
                        <span class="text-slate-600 dark:text-slate-300"><?= esc($a['description']) ?></span>
                        <?php if ($url && $a['entity_id']): ?><a href="<?= site_url($url . $a['entity_id']) ?>" class="text-crimson-700 hover:underline dark:text-crimson-400">View</a><?php endif; ?>
                    </div>
                    <span class="shrink-0 text-xs text-slate-400"><?= time_ago($a['created_at']) ?></span>
                </li>
            <?php endforeach; ?>
            <?php if (!$recent): ?><li><?= view('partials/empty', ['icon' => 'clock', 'title' => 'No activity yet']) ?></li><?php endif; ?>
        </ul>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Low stock alerts</div><a href="<?= site_url('inventory?filter=low') ?>" class="btn-ghost btn-sm">Inventory →</a></div>
        <ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($lowStock as $p): ?>
                <li class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold"><?= esc($p['name']) ?></div><div class="text-xs text-slate-500"><?= esc($p['sku']) ?></div></div>
                    <span class="<?= (float) $p['qty_on_hand'] <= 0 ? 'badge-red' : 'badge-amber' ?>"><?= (float) $p['qty_on_hand'] ?> left</span>
                </li>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><li><?= view('partials/empty', ['icon' => 'box', 'title' => 'Stock levels healthy']) ?></li><?php endif; ?>
        </ul>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/chart.umd.js') ?>"></script>
<script>
(function () {
    const series = <?= json_encode($series) ?>, byType = <?= json_encode($byType) ?>, byMethod = <?= json_encode($byMethod) ?>, status = <?= json_encode($jobStatus) ?>;
    const charts = [];
    function build() {
        charts.forEach(c => c.destroy()); charts.length = 0;
        wtChartDefaults();
        const ctx = document.getElementById('trendChart').getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 280); grad.addColorStop(0, 'rgba(155,22,49,.35)'); grad.addColorStop(1, 'rgba(155,22,49,0)');
        charts.push(new Chart(ctx, {
            data: { labels: series.labels, datasets: [
                { type: 'line', label: 'Revenue', data: series.revenue, borderColor: '#9b1631', backgroundColor: grad, fill: true, tension: .35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5 },
                { type: 'line', label: 'Collected', data: series.collected, borderColor: '#10b981', borderDash: [5, 4], tension: .35, borderWidth: 2, pointRadius: 0 },
                { type: 'bar', label: 'Expenses', data: series.expenses, backgroundColor: 'rgba(82,91,181,.35)', borderRadius: 6, maxBarThickness: 18 },
            ] },
            options: { interaction: { mode: 'index', intersect: false }, scales: { y: { ticks: { callback: wtMoneyTick } }, x: { grid: { display: false } } }, plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + money(c.raw) } } } },
        }));
        const doughnut = (id, rows, money_ = true) => {
            const el = document.getElementById(id); if (!el) return;
            charts.push(new Chart(el, { type: 'doughnut', data: { labels: rows.map(r => r.label), datasets: [{ data: rows.map(r => r.value), backgroundColor: rows.map((r, i) => r.color || WT_COLORS[i % WT_COLORS.length]), borderWidth: 0, hoverOffset: 8 }] },
                options: { cutout: '68%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => c.label + ': ' + (money_ ? money(c.raw) : c.raw) } } } } }));
        };
        doughnut('mixChart', byType);
        doughnut('methodChart', byMethod);
        charts.push(new Chart(document.getElementById('statusChart'), { type: 'pie', data: { labels: status.map(s => s.label), datasets: [{ data: status.map(s => s.value), backgroundColor: status.map(s => s.color), borderWidth: 2, borderColor: document.documentElement.classList.contains('dark') ? '#0b0d2e' : '#fff' }] },
            options: { plugins: { legend: { position: 'right' } } } }));
    }
    build();
    window.addEventListener('theme-changed', () => setTimeout(build, 50));
})();
</script>
<?= $this->endSection() ?>
