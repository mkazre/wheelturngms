<?php
$presets = [
    'This month' => [date('Y-m-01'), date('Y-m-d')],
    'Last month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Last 7 days' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'This quarter' => [date('Y-') . str_pad((string) (intdiv((int) date('n') - 1, 3) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01', date('Y-m-d')],
    'Tax year' => [(int) date('n') >= 3 ? date('Y-03-01') : (date('Y') - 1) . '-03-01', date('Y-m-d')],
    'This year' => [date('Y-01-01'), date('Y-m-d')],
];
$qs = http_build_query(['from' => $from, 'to' => $to]);
$isStatement = !empty($statement);
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => esc($title), 'subtitle' => esc(implode(' · ', array_map(static fn ($k, $v) => "$k: $v", array_keys($meta), $meta))), 'crumbs' => ['reports' => 'Reports'],
    'actions' => '<a href="' . site_url('reports/' . $slug . '/export/xlsx?' . $qs) . '" class="btn-light"><span class="badge-green">XLSX</span> Excel</a><a href="' . site_url('reports/' . $slug . '/export/pdf?' . $qs) . '" class="btn-dark"><span class="badge-red">PDF</span> Download</a>']) ?>

<?php if ($dated): ?>
<form class="card mb-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
    <div class="flex flex-wrap gap-1.5 lg:flex-1">
        <?php foreach ($presets as $l => [$f, $t]): ?>
            <a href="?from=<?= $f ?>&to=<?= $t ?>" class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $from === $f && $to === $t ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($single_date)): ?><div><label class="label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="input"></div><?php else: ?><input type="hidden" name="from" value="<?= esc($from) ?>"><?php endif; ?>
    <div><label class="label"><?= empty($single_date) ? 'To' : 'As at' ?></label><input type="date" name="to" value="<?= esc($to) ?>" class="input"></div>
    <button class="btn-primary">Run report</button>
</form>
<?php endif; ?>

<?php if ($kpis): ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?php foreach ($kpis as $i => [$label, $value]): ?>
        <?= view('partials/kpi', ['label' => $label, 'value' => is_string($value) ? $value : number_format((float) $value), 'icon' => ['chart', 'wallet', 'zap', 'pie'][$i % 4], 'tone' => ['crimson', 'green', 'violet', 'navy'][$i % 4]]) ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid gap-6 <?= $chart ? 'xl:grid-cols-3' : '' ?>">
    <?php if ($chart): ?>
    <div class="card xl:order-2"><div class="card-header"><div class="card-title">Visual</div></div><div class="card-body"><div class="h-80"><canvas id="reportChart"></canvas></div></div></div>
    <?php endif; ?>
    <div class="card <?= $chart ? 'xl:col-span-2' : '' ?> xl:order-1">
        <?php if ($note): ?><div class="border-b border-slate-100 bg-amber-50/60 px-5 py-3 text-xs text-amber-900 dark:border-white/10 dark:bg-amber-500/10 dark:text-amber-200"><?= esc($note) ?></div><?php endif; ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><?php foreach ($columns as $c): ?><th class="<?= in_array($c['type'] ?? '', ['money', 'number'], true) ? 'text-right' : '' ?>"><?= esc($c['label']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $isHead = $isStatement && ($r['section'] ?? '') !== '' ; $isSection = $isHead && ($r['amount'] ?? null) === null; ?>
                <tr class="<?= $isSection ? 'bg-slate-50 dark:bg-white/5' : '' ?>">
                    <?php foreach ($columns as $k => $c): $v = $r[$k] ?? ''; $t = $c['type'] ?? 'text'; ?>
                        <td class="<?= in_array($t, ['money', 'number'], true) ? 'text-right' : '' ?> <?= $isHead ? 'font-bold text-slate-900 dark:text-white' : '' ?> <?= $isSection ? 'text-[11px] uppercase tracking-wider' : '' ?>">
                            <?php if ($t === 'money'): ?><?= $v === null || $v === '' ? '' : money($v) ?>
                            <?php elseif ($t === 'number'): ?><?= $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?>
                            <?php else: ?><?= esc((string) $v) ?><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="py-12 text-center text-slate-500">No data for this period.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($totals): ?>
            <tfoot><tr class="border-t-2 border-navy-900 dark:border-white/30">
                <?php foreach ($columns as $k => $c): $v = $totals[$k] ?? ''; ?>
                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-white <?= in_array($c['type'] ?? '', ['money', 'number'], true) ? 'text-right' : '' ?>"><?= ($c['type'] ?? '') === 'money' && $v !== '' ? money($v) : esc((string) $v) ?></td>
                <?php endforeach; ?>
            </tr></tfoot>
            <?php endif; ?>
        </table></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($chart): ?>
<script src="<?= base_url('assets/vendor/chart.umd.js') ?>"></script>
<script>
(function () {
    const def = <?= json_encode($chart) ?>;
    let chart;
    function build() {
        if (chart) chart.destroy();
        wtChartDefaults();
        const round = ['pie', 'doughnut'].includes(def.type);
        const isMoney = !def.count;
        chart = new Chart(document.getElementById('reportChart'), {
            type: def.type,
            data: { labels: def.labels, datasets: def.datasets.map((d, i) => ({
                label: d.label, data: d.data,
                backgroundColor: round ? WT_COLORS : (i === 0 ? '#9b1631' : '#525bb5'),
                borderRadius: round ? 0 : 6, borderWidth: round ? 2 : 0, borderColor: document.documentElement.classList.contains('dark') ? '#0b0d2e' : '#fff', maxBarThickness: 36,
            })) },
            options: {
                indexAxis: def.horizontal ? 'y' : 'x',
                cutout: def.type === 'doughnut' ? '62%' : undefined,
                plugins: { legend: { display: round || def.datasets.length > 1, position: 'bottom' }, tooltip: { callbacks: { label: c => (c.dataset.label || c.label) + ': ' + (isMoney ? money(c.raw) : c.raw) } } },
                scales: round ? {} : { [def.horizontal ? 'x' : 'y']: { ticks: { callback: isMoney ? wtMoneyTick : undefined } }, [def.horizontal ? 'y' : 'x']: { grid: { display: false } } },
            },
        });
    }
    build();
    window.addEventListener('theme-changed', () => setTimeout(build, 50));
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
