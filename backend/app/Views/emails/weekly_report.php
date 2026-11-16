<?php

use App\Libraries\Reports;

/** Email body for the Monday report. Inline styles only (email-client safe). */
$d = static function ($now, $before) {
    $pct = Reports::delta((float) $now, (float) $before);
    if ($pct === null) {
        return '<span style="color:#64748b;font-size:11px;">new</span>';
    }
    $up = $pct >= 0;
    return '<span style="display:inline-block;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:700;background:' . ($up ? '#d1fae5;color:#065f46' : '#fee2e2;color:#991b1b') . ';">' . ($up ? '▲' : '▼') . ' ' . number_format(abs($pct), 1) . '%</span>';
};
$rows = [
    ['Revenue (excl. VAT)', 'revenue', true],
    ['Gross profit', 'gross_profit', true],
    ['Net profit', 'net_profit', true],
    ['Cash collected', 'collected', true],
    ['Expenses', 'expenses', true],
    ['Invoices issued', 'invoices', false],
    ['Average invoice', 'avg_invoice', true],
    ['Jobs completed', 'jobs_completed', false],
    ['New customers', 'new_customers', false],
];
$fmt = static fn ($v, $isMoney) => $isMoney ? money($v) : number_format((float) $v);
$th = 'style="padding:8px 10px;text-align:right;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;border-bottom:2px solid #0b0d2e;"';
$td = 'style="padding:9px 10px;text-align:right;border-bottom:1px solid #eef2f7;font-size:13px;"';
$card = 'style="background:#f8fafc;border-radius:12px;padding:14px 16px;"';
?>
<p style="margin:0 0 4px 0;font-size:13px;color:#9b1631;font-weight:700;">MONDAY BRIEFING</p>
<h1 style="margin:0 0 6px 0;font-size:22px;color:#0b0d2e;">Last week at Wheelsturn</h1>
<p style="margin:0 0 20px 0;color:#64748b;font-size:13px;">Week <?= fdate($p['weekStart']) ?> – <?= fdate($p['weekEnd']) ?> · Month to date <?= fdate($p['mtdStart']) ?> – <?= fdate($p['mtdEnd']) ?></p>

<!-- Hero KPIs -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
    <?php foreach ([['Revenue', $week['revenue'], $prev['revenue'], '#9b1631'], ['Collected', $week['collected'], $prev['collected'], '#059669'], ['Net profit', $week['net_profit'], $prev['net_profit'], '#1c205c']] as [$l, $v, $b, $c]): ?>
    <td width="33%" style="padding:4px;"><div style="background:#ffffff;border:1px solid #e2e8f0;border-top:4px solid <?= $c ?>;border-radius:12px;padding:14px;">
        <div style="font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#64748b;font-weight:700;"><?= $l ?></div>
        <div style="font-size:20px;font-weight:800;color:#0b0d2e;margin:4px 0;"><?= money($v) ?></div>
        <?= $d($v, $b) ?> <span style="font-size:11px;color:#94a3b8;">vs prior week</span>
    </div></td>
    <?php endforeach; ?>
</tr></table>

<!-- Comparison table -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;border-collapse:collapse;">
    <tr><th style="padding:8px 10px;text-align:left;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;border-bottom:2px solid #0b0d2e;">Metric</th><th <?= $th ?>>Last week</th><th <?= $th ?>>vs prior</th><th <?= $th ?>>Month to date</th><th <?= $th ?>>vs last month</th></tr>
    <?php foreach ($rows as [$label, $key, $isMoney]): ?>
        <tr><td style="padding:9px 10px;border-bottom:1px solid #eef2f7;font-size:13px;color:#334155;"><?= $label ?></td>
            <td <?= $td ?>><b><?= $fmt($week[$key], $isMoney) ?></b></td><td <?= $td ?>><?= $d($week[$key], $prev[$key]) ?></td>
            <td <?= $td ?>><b><?= $fmt($mtd[$key], $isMoney) ?></b></td><td <?= $td ?>><?= $d($mtd[$key], $lm[$key]) ?></td></tr>
    <?php endforeach; ?>
    <tr><td style="padding:9px 10px;font-size:13px;color:#334155;">Gross margin</td><td <?= $td ?>><b><?= number_format($week['gross_margin'], 1) ?>%</b></td><td <?= $td ?>></td><td <?= $td ?>><b><?= number_format($mtd['gross_margin'], 1) ?>%</b></td><td <?= $td ?>></td></tr>
</table>

<!-- Charts -->
<h3 style="margin:26px 0 8px 0;font-size:15px;color:#0b0d2e;">Daily revenue, last week</h3>
<img src="{{cid:daily-revenue.png}}" width="540" alt="Daily revenue chart" style="display:block;max-width:100%;height:auto;border-radius:10px;border:1px solid #eef2f7;">
<h3 style="margin:22px 0 8px 0;font-size:15px;color:#0b0d2e;">Revenue mix, month to date</h3>
<img src="{{cid:revenue-mix.png}}" width="520" alt="Revenue mix chart" style="display:block;max-width:100%;height:auto;border-radius:10px;border:1px solid #eef2f7;">

<!-- Two columns: technicians & services -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;"><tr>
    <td width="50%" valign="top" style="padding-right:8px;"><div <?= $card ?>>
        <div style="font-weight:700;color:#0b0d2e;margin-bottom:8px;"><span style="display:inline-block;width:4px;height:14px;background:#9b1631;border-radius:2px;vertical-align:-2px;margin-right:8px;"></span>Technician leaderboard</div>
        <?php foreach ($techs as $i => $t): ?>
            <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #e2e8f0;"><b><?= $i + 1 ?>. <?= esc($t['name']) ?></b><br><span style="color:#64748b;"><?= money_short($t['revenue']) ?> · <?= $t['hours_sold'] ?>h sold · <?= $t['efficiency'] ?>% eff.</span></div>
        <?php endforeach; ?>
        <?php if (!$techs): ?><div style="font-size:12px;color:#64748b;">No technician data.</div><?php endif; ?>
    </div></td>
    <td width="50%" valign="top" style="padding-left:8px;"><div <?= $card ?>>
        <div style="font-weight:700;color:#0b0d2e;margin-bottom:8px;"><span style="display:inline-block;width:4px;height:14px;background:#9b1631;border-radius:2px;vertical-align:-2px;margin-right:8px;"></span>Top sellers</div>
        <?php foreach ($services as $s): ?>
            <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #e2e8f0;"><?= esc(mb_strimwidth($s['description'], 0, 34, '…')) ?><span style="float:right;font-weight:700;"><?= money_short($s['amount']) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$services): ?><div style="font-size:12px;color:#64748b;">No sales last week.</div><?php endif; ?>
    </div></td>
</tr></table>

<!-- Cash & workshop -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;"><tr>
    <td width="50%" valign="top" style="padding-right:8px;"><div <?= $card ?>>
        <div style="font-weight:700;color:#0b0d2e;margin-bottom:8px;"><span style="display:inline-block;width:4px;height:14px;background:#9b1631;border-radius:2px;vertical-align:-2px;margin-right:8px;"></span>Debtors &amp; cash</div>
        <div style="font-size:12px;line-height:1.9;">Total owed: <b><?= money($debtors['total']) ?></b><br>Overdue: <b style="color:#b91c1c;"><?= money($debtors['overdue']) ?></b><br>90+ days: <b><?= money($debtors['d90']) ?></b><br>VAT payable MTD: <b><?= money($vat['payable']) ?></b></div>
        <?php foreach ($methods as $m): ?><div style="font-size:11px;color:#64748b;"><?= esc($m['label']) ?>: <?= money($m['value']) ?></div><?php endforeach; ?>
    </div></td>
    <td width="50%" valign="top" style="padding-left:8px;"><div <?= $card ?>>
        <div style="font-weight:700;color:#0b0d2e;margin-bottom:8px;"><span style="display:inline-block;width:4px;height:14px;background:#9b1631;border-radius:2px;vertical-align:-2px;margin-right:8px;"></span>Workshop right now</div>
        <div style="font-size:12px;line-height:1.9;">
            <?php foreach (['checked_in' => 'Checked in', 'in_progress' => 'In progress', 'awaiting_parts' => 'Awaiting parts', 'awaiting_approval' => 'Awaiting approval', 'quality_check' => 'Quality check', 'completed' => 'Ready for collection'] as $k => $l): ?>
                <?= $l ?>: <b><?= (int) ($workshop[$k] ?? 0) ?></b><br>
            <?php endforeach; ?>
            Low-stock items: <b><?= $lowStock ?></b>
        </div>
    </div></td>
</tr></table>

<?php if ($debtors['top']): ?>
<h3 style="margin:22px 0 8px 0;font-size:15px;color:#0b0d2e;">Follow up this week</h3>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
    <?php foreach ($debtors['top'] as $a): ?><tr><td style="padding:6px 0;border-bottom:1px solid #eef2f7;"><?= esc($a['name']) ?> <span style="color:#94a3b8;"><?= esc($a['phone']) ?></span></td><td style="padding:6px 0;border-bottom:1px solid #eef2f7;text-align:right;font-weight:700;"><?= money($a['total']) ?></td></tr><?php endforeach; ?>
</table>
<?php endif; ?>

<?= view('emails/_pay_button', ['url' => site_url('dashboard'), 'label' => 'Open live dashboard']) ?>
<p style="font-size:11px;color:#94a3b8;">The full report is attached as a PDF. Sent automatically every Monday morning. Manage recipients in Settings → Notifications.</p>
