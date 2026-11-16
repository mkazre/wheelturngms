<?php
/** @var array $job @var array $items @var array $customer @var ?array $vehicle @var array $inspections @var array $mechanics @var ?array $advisor @var bool $showPrices @var string $logo */
$statuses = job_statuses();
$labels = ['ok' => 'GOOD', 'advisory' => 'SOON', 'urgent' => 'URGENT', 'na' => 'N/A'];
$checked = array_filter($inspections, static fn ($i) => !empty($i['status']));
?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'JOB CARD', 'docNumber' => $job['number'], 'meta' => [
    'Status' => esc($statuses[$job['status']]['label'] ?? $job['status']), 'Checked in' => $job['checked_in_at'] ? fdatetime($job['checked_in_at']) : fdatetime($job['created_at']),
    'Promised' => $job['promised_at'] ? fdatetime($job['promised_at']) : null, 'Advisor' => $advisor ? esc($advisor['first_name'] . ' ' . $advisor['last_name']) : null,
    'Technicians' => esc(implode(', ', array_map(static fn ($m) => $m['first_name'], $mechanics))) ?: null,
]]) ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => $vehicle, 'job' => $job, 'partyLabel' => 'Customer']) ?>

<table style="margin-top: 10px;"><tr>
    <td class="small" style="width: 25%;"><span class="muted">Odometer in:</span> <b><?= $job['odometer_in'] ? number_format($job['odometer_in']) . ' km' : '—' ?></b></td>
    <td class="small" style="width: 25%;"><span class="muted">Fuel:</span> <b><?= esc($job['fuel_level'] ?: '—') ?></b></td>
    <td class="small" style="width: 25%;"><span class="muted">Job type:</span> <b><?= esc($job['job_type'] ?: '—') ?></b></td>
    <td class="small" style="width: 25%;"><span class="muted">Bay:</span> <b><?= esc($job['bay'] ?: '—') ?></b></td>
</tr></table>

<?php foreach (['customer_complaint' => 'Customer complaint / request', 'diagnosis' => 'Diagnosis', 'work_performed' => 'Work performed', 'recommendations' => 'Recommendations'] as $f => $l): if (!$job[$f]) continue; ?>
    <div style="margin-top: 10px;"><div class="label"><?= $l ?></div><div><?= nl2br(esc($job[$f])) ?></div></div>
<?php endforeach; ?>

<?= view('pdf/_items', ['items' => $items, 'doc' => $job, 'showPrices' => $showPrices]) ?>

<?php if ($checked): ?>
<div class="label" style="margin-top: 16px;">Vehicle inspection</div>
<table class="items">
    <thead><tr><th>Area</th><th>Check</th><th style="width: 12%;">Result</th><th>Note</th></tr></thead>
    <tbody><?php foreach ($checked as $i): ?>
        <tr><td class="muted"><?= esc($i['section']) ?></td><td><?= esc($i['item']) ?></td><td><span class="pill <?= esc($i['status']) ?>"><?= $labels[$i['status']] ?? '' ?></span></td><td class="small"><?= esc($i['note']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
<?php endif; ?>

<?php $marks = json_decode((string) ($job['damage_marks'] ?? ''), true) ?: []; if ($marks): $zl = ['front_bumper' => 'Front bumper', 'bonnet' => 'Bonnet', 'windscreen' => 'Windscreen', 'roof' => 'Roof', 'rear_window' => 'Rear window', 'boot' => 'Boot / tailgate', 'rear_bumper' => 'Rear bumper', 'lf_fender' => 'Left front fender', 'lf_door' => 'Left front door', 'lr_door' => 'Left rear door', 'lr_quarter' => 'Left rear quarter', 'rf_fender' => 'Right front fender', 'rf_door' => 'Right front door', 'rr_door' => 'Right rear door', 'rr_quarter' => 'Right rear quarter', 'l_mirror' => 'Left mirror', 'r_mirror' => 'Right mirror']; ?>
<div class="label" style="margin-top: 16px;">Existing damage recorded at check-in</div>
<table class="items"><thead><tr><th>Panel</th><th>Damage</th><th>Note</th></tr></thead><tbody>
<?php foreach ($marks as $z => $m): ?><tr><td><?= esc($zl[$z] ?? $z) ?></td><td><?= esc(ucfirst($m['type'])) ?></td><td class="small"><?= esc($m['note']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<div class="small muted" style="margin-top: 16px;"><?= nl2br(esc(setting('job_card_terms'))) ?></div>
<table style="margin-top: 20px;"><tr>
    <td style="width: 50%; padding-right: 20px; vertical-align: bottom;">
        <?php if ($job['customer_signature']): ?><img src="<?= $job['customer_signature'] ?>" style="height: 50px;"><?php else: ?><div style="height: 40px;"></div><?php endif; ?>
        <div style="border-top: 1px solid #94a3b8;" class="small muted">Customer signature<?= $job['signed_at'] ? ' · signed ' . fdatetime($job['signed_at']) : '' ?></div>
    </td>
    <td style="width: 32%; vertical-align: bottom;"><div style="height: 40px;"></div><div style="border-top: 1px solid #94a3b8;" class="small muted">Vehicle collected (signature &amp; date)</div></td>
    <td style="width: 18%; text-align: right; vertical-align: bottom;"><img src="<?= \App\Libraries\Notify::qr(site_url('track/' . $job['public_token']), 3) ?>" style="width:70px;height:70px;"><div class="small muted">Track live</div></td>
</tr></table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · Job card <?= esc($job['number']) ?></div>
</body></html>
