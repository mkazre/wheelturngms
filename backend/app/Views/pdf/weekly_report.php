<?php

use App\Libraries\Reports;

/** PDF attachment for the Monday report, with CSS bar charts (Dompdf-safe). */
$delta = static function ($a, $b) {
    $pct = Reports::delta((float) $a, (float) $b);
    if ($pct === null) {
        return '<span class="muted">new</span>';
    }
    return '<span style="color:' . ($pct >= 0 ? '#059669' : '#dc2626') . ';font-weight:bold;">' . ($pct >= 0 ? '+' : '−') . number_format(abs($pct), 1) . '%</span>';
};
$metrics = [['Revenue (excl. VAT)', 'revenue', 1], ['Cost of sales', 'cost_of_sales', 1], ['Gross profit', 'gross_profit', 1], ['Expenses', 'expenses', 1], ['Net profit', 'net_profit', 1], ['Cash collected', 'collected', 1], ['Invoices', 'invoices', 0], ['Average invoice', 'avg_invoice', 1], ['Jobs opened', 'jobs_opened', 0], ['Jobs completed', 'jobs_completed', 0], ['New customers', 'new_customers', 0], ['Quote win rate %', 'quote_win_rate', 0]];
$maxDay = max(1, max($daily['revenue'] ?: [1]));
$mixTotal = array_sum(array_column($mix, 'value')) ?: 1;
$colors = ['#9b1631', '#1c205c', '#ef1c25', '#525bb5', '#f59e0b', '#10b981', '#8b5cf6'];
?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'WEEKLY PERFORMANCE', 'docNumber' => 'Week ' . date('W', strtotime($p['weekStart'])), 'meta' => ['Week' => fdate($p['weekStart']) . ' – ' . fdate($p['weekEnd']), 'Month to date' => fdate($p['mtdStart']) . ' – ' . fdate($p['mtdEnd'])]]) ?>

<table class="items" style="margin-top: 16px;">
    <thead><tr><th>Metric</th><th class="right">Last week</th><th class="right">Prior week</th><th class="right">Change</th><th class="right">Month to date</th><th class="right">Same days last month</th><th class="right">Change</th></tr></thead>
    <tbody>
    <?php foreach ($metrics as [$l, $k, $m]): $f = static fn ($v) => $m ? money($v) : number_format((float) $v, $k === 'quote_win_rate' ? 1 : 0); ?>
        <tr><td class="bold"><?= $l ?></td><td class="right bold"><?= $f($week[$k]) ?></td><td class="right muted"><?= $f($prev[$k]) ?></td><td class="right"><?= $delta($week[$k], $prev[$k]) ?></td>
            <td class="right bold"><?= $f($mtd[$k]) ?></td><td class="right muted"><?= $f($lm[$k]) ?></td><td class="right"><?= $delta($mtd[$k], $lm[$k]) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table style="margin-top: 18px;"><tr>
    <td style="width: 55%; vertical-align: top; padding-right: 12px;">
        <div class="label">Daily revenue, last week</div>
        <table style="height: 150px;"><tr>
            <?php foreach ($daily['revenue'] as $i => $v): $h = (int) round($v / $maxDay * 120); ?>
                <td style="vertical-align: bottom; text-align: center; width: 14%;">
                    <div class="small muted"><?= $v >= 1000 ? 'R' . round($v / 1000, 1) . 'k' : 'R' . round($v) ?></div>
                    <div style="height: <?= max(1, $h) ?>px; background: #9b1631; margin: 2px 6px 0 6px;"></div>
                    <div class="small bold" style="border-top: 1px solid #cbd5e1; padding-top: 2px;"><?= esc($daily['labels'][$i]) ?></div>
                </td>
            <?php endforeach; ?>
        </tr></table>
    </td>
    <td style="width: 45%; vertical-align: top;">
        <div class="label">Revenue mix, month to date</div>
        <?php foreach ($mix as $i => $m): $pct = round($m['value'] / $mixTotal * 100); ?>
            <div style="margin: 5px 0;"><span class="small"><?= esc($m['label']) ?></span><span class="small bold" style="float: right;"><?= money($m['value']) ?> · <?= $pct ?>%</span>
                <div style="background: #e2e8f0; height: 7px; border-radius: 4px; margin-top: 2px;"><div style="width: <?= $pct ?>%; height: 7px; border-radius: 4px; background: <?= $colors[$i % 7] ?>;"></div></div></div>
        <?php endforeach; ?>
        <?php if (!$mix): ?><div class="muted small">No sales this month yet.</div><?php endif; ?>
    </td>
</tr></table>

<table style="margin-top: 16px;"><tr>
    <td style="width: 50%; vertical-align: top; padding-right: 8px;">
        <div class="label">Technician leaderboard (last week)</div>
        <table class="items"><thead><tr><th>Technician</th><th class="right">Jobs</th><th class="right">Hrs sold</th><th class="right">Eff.</th><th class="right">Revenue</th></tr></thead><tbody>
        <?php foreach ($techs as $t): ?><tr><td><?= esc($t['name']) ?></td><td class="right"><?= $t['jobs'] ?></td><td class="right"><?= $t['hours_sold'] ?></td><td class="right"><?= $t['efficiency'] ?>%</td><td class="right bold"><?= money($t['revenue']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </td>
    <td style="width: 50%; vertical-align: top; padding-left: 8px;">
        <div class="label">Top services &amp; parts (last week)</div>
        <table class="items"><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Revenue</th></tr></thead><tbody>
        <?php foreach ($services as $s): ?><tr><td><?= esc(mb_strimwidth($s['description'], 0, 40, '…')) ?></td><td class="right"><?= (float) $s['qty'] ?></td><td class="right bold"><?= money($s['amount']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </td>
</tr></table>

<table style="margin-top: 16px;"><tr>
    <td style="width: 33%; padding-right: 6px;"><div class="box"><div class="label">Debtors</div><div class="bold" style="font-size: 13px;"><?= money($debtors['total']) ?></div><div class="small">Overdue <?= money($debtors['overdue']) ?> · 90+ <?= money($debtors['d90']) ?></div></div></td>
    <td style="width: 33%; padding: 0 3px;"><div class="box"><div class="label">VAT (MTD)</div><div class="bold" style="font-size: 13px;"><?= money($vat['payable']) ?></div><div class="small">Output <?= money($vat['output_vat']) ?> · Input <?= money($vat['input_vat']) ?></div></div></td>
    <td style="width: 33%; padding-left: 6px;"><div class="box"><div class="label">Workshop now</div><div class="bold" style="font-size: 13px;"><?= array_sum(array_map('intval', $workshop)) ?> vehicles</div><div class="small">Awaiting parts <?= (int) ($workshop['awaiting_parts'] ?? 0) ?> · Ready <?= (int) ($workshop['completed'] ?? 0) ?></div></div></td>
</tr></table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · Weekly performance report · generated <?= date('d M Y H:i') ?></div>
</body></html>
