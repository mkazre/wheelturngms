<?php /** @var array $po @var array $items @var array $supplier @var ?array $job @var string $logo */ ?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'PURCHASE ORDER', 'docNumber' => $po['number'], 'meta' => [
    'Order date' => fdate($po['order_date']), 'Required by' => $po['expected_date'] ? fdate($po['expected_date']) : null, 'Job card' => $job ? esc($job['number'] . ' · ' . $job['registration']) : null,
]]) ?>
<table style="margin-top: 14px;"><tr><td style="width: 50%; padding-right: 6px;"><div class="box">
    <div class="label">Supplier</div><div class="bold" style="font-size: 10px;"><?= esc($supplier['name']) ?></div>
    <div class="small"><?= esc($supplier['contact_person']) ?><br><?= nl2br(esc($supplier['address'])) ?><br><?= esc(implode(' · ', array_filter([$supplier['phone'], $supplier['email']]))) ?><?= $supplier['vat_number'] ? '<br>VAT No: ' . esc($supplier['vat_number']) : '' ?><?= $supplier['account_number'] ? '<br>Our account: ' . esc($supplier['account_number']) : '' ?></div>
</div></td><td style="width: 50%; padding-left: 6px;"><div class="box">
    <div class="label">Deliver to</div><div class="bold" style="font-size: 10px;"><?= esc(setting('company_name')) ?></div><div class="small"><?= nl2br(esc(setting('company_address'))) ?><br><?= esc(setting('company_phone')) ?></div>
</div></td></tr></table>
<?= view('pdf/_items', ['items' => $items, 'doc' => $po, 'left' => $po['notes'] ? '<div class="label">Notes</div><div class="small">' . nl2br(esc($po['notes'])) . '</div>' : '']) ?>
<div class="small muted" style="margin-top: 16px;">Please quote PO number <b><?= esc($po['number']) ?></b> on your tax invoice and delivery note. Goods not matching this order may be returned.</div>
<table style="margin-top: 30px;"><tr><td style="width: 50%;"><div style="border-bottom: 1px solid #94a3b8; height: 30px;"></div><div class="small muted">Authorised by</div></td><td></td></tr></table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · Purchase order <?= esc($po['number']) ?></div>
</body></html>
