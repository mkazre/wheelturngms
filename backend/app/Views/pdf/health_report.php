<?php
/** Vehicle health report: traffic-light summary of the digital inspection. */
$groups = [];
foreach ($inspections as $i) {
    $groups[$i['section'] ?: 'General'][] = $i;
}
$count = ['ok' => 0, 'advisory' => 0, 'urgent' => 0];
foreach ($inspections as $i) {
    if (isset($count[$i['status']])) {
        $count[$i['status']]++;
    }
}
$dot = ['ok' => '#10b981', 'advisory' => '#f59e0b', 'urgent' => '#ef4444', 'na' => '#cbd5e1', '' => '#e2e8f0'];
$word = ['ok' => 'Good', 'advisory' => 'Needs attention soon', 'urgent' => 'Needs attention now', 'na' => 'N/A', '' => 'Not checked'];
?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'VEHICLE HEALTH REPORT', 'docNumber' => $job['number'], 'meta' => ['Date' => fdate($job['created_at']), 'Odometer' => $job['odometer_in'] ? number_format($job['odometer_in']) . ' km' : null]]) ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => $vehicle, 'job' => $job, 'partyLabel' => 'Prepared for']) ?>
<table style="margin-top: 14px;"><tr>
    <?php foreach ([['ok', 'Good condition'], ['advisory', 'Attention soon'], ['urgent', 'Attention now']] as [$k, $l]): ?>
        <td style="width: 33%; padding: 0 4px;"><div class="box" style="border-left: 5px solid <?= $dot[$k] ?>;"><div style="font-size: 20px; font-weight: bold; color: <?= $dot[$k] ?>;"><?= $count[$k] ?></div><div class="small"><?= $l ?></div></div></td>
    <?php endforeach; ?>
</tr></table>
<?php foreach ($groups as $section => $items): ?>
    <div class="label" style="margin-top: 14px;"><?= esc($section) ?></div>
    <table class="items"><tbody>
        <?php foreach ($items as $i): $s = (string) $i['status']; ?>
            <tr><td style="width: 4%;"><span style="display:inline-block;width:10px;height:10px;border-radius:5px;background:<?= $dot[$s] ?? '#e2e8f0' ?>;"></span></td>
                <td style="width: 40%;" class="bold"><?= esc($i['item']) ?></td><td style="width: 22%;"><?= $word[$s] ?? '' ?></td><td class="small"><?= esc($i['note']) ?><?= (float) $i['estimate'] > 0 ? ' · est. ' . money($i['estimate']) : '' ?></td></tr>
        <?php endforeach; ?>
    </tbody></table>
<?php endforeach; ?>
<table style="margin-top: 18px;"><tr><td class="small muted">Approve recommended work online, view photos and track progress by scanning the code, or visit <?= esc(site_url('track/' . $job['public_token'])) ?></td>
    <td style="width: 80px; text-align: right;"><img src="<?= \App\Libraries\Notify::qr(site_url('track/' . $job['public_token']), 3) ?>" style="width: 72px; height: 72px;"></td></tr></table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · Vehicle health report <?= esc($job['number']) ?></div>
</body></html>
