<?php
/** @var array $quote @var array $items @var array $customer @var ?array $vehicle @var string $logo */
$left = '';
if (!empty($quote['notes'])) {
    $left .= '<div class="label">Notes</div><div class="small" style="margin-bottom:8px;">' . nl2br(esc($quote['notes'])) . '</div>';
}
$left .= '<table><tr><td style="width:70px;"><img src="' . \App\Libraries\Notify::qr(site_url('q/' . $quote['public_token']), 3) . '" style="width:64px;height:64px;"></td><td class="small" style="vertical-align:middle;"><b>Scan to accept online</b><br><span style="color:#9b1631">' . esc(site_url('q/' . $quote['public_token'])) . '</span></td></tr></table>';
?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'QUOTATION', 'docNumber' => $quote['number'], 'meta' => ['Quote date' => fdate($quote['issue_date']), 'Valid until' => fdate($quote['expiry_date'])]]) ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => $vehicle, 'job' => null, 'partyLabel' => 'Prepared for']) ?>
<?= view('pdf/_items', ['items' => $items, 'doc' => $quote, 'left' => $left]) ?>
<?php if ($quote['terms']): ?><div style="margin-top: 16px;" class="small muted"><b>Terms:</b> <?= nl2br(esc($quote['terms'])) ?></div><?php endif; ?>
<table style="margin-top: 30px;"><tr>
    <td style="width: 50%; padding-right: 20px;"><div style="border-bottom: 1px solid #94a3b8; height: 30px;"></div><div class="small muted">Customer signature &amp; date (acceptance)</div></td>
    <td style="width: 50%;"><div style="border-bottom: 1px solid #94a3b8; height: 30px;"></div><div class="small muted">For <?= esc(setting('company_name')) ?></div></td>
</tr></table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · This is a quotation, not a tax invoice.</div>
</body></html>
