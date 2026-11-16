<?php /** @var array $customer @var string $from @var string $to @var float $opening @var array $lines @var float $closing @var ?array $ageing @var string $logo */ ?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'STATEMENT', 'docNumber' => $customer['code'] ?? '', 'meta' => ['Period' => fdate($from) . ' – ' . fdate($to), 'Statement date' => fdate(date('Y-m-d'))]]) ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => null, 'job' => null, 'partyLabel' => 'Account']) ?>
<table class="items" style="margin-top: 14px;">
    <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead>
    <tbody>
        <tr><td><?= fdate($from) ?></td><td></td><td class="bold">Opening balance</td><td></td><td></td><td class="right bold"><?= money($opening) ?></td></tr>
        <?php foreach ($lines as $l): ?>
            <tr><td><?= fdate($l['date']) ?></td><td><?= esc($l['ref']) ?></td><td><?= esc($l['desc']) ?></td><td class="right"><?= $l['debit'] ? money($l['debit']) : '' ?></td><td class="right"><?= $l['credit'] ? money($l['credit']) : '' ?></td><td class="right"><?= money($l['balance']) ?></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>
<table style="margin-top: 14px;"><tr>
    <td style="width: 60%;">
        <table class="items"><thead><tr><th class="right">Current</th><th class="right">1–30 days</th><th class="right">31–60 days</th><th class="right">61–90 days</th><th class="right">90+ days</th></tr></thead>
            <tbody><tr><?php foreach (['current', 'd30', 'd60', 'd90', 'd90plus'] as $k): ?><td class="right"><?= money($ageing[$k] ?? 0) ?></td><?php endforeach; ?></tr></tbody></table>
    </td>
    <td style="width: 40%; padding-left: 14px;"><table class="totals"><tr class="due"><td>AMOUNT DUE</td><td class="right"><?= money(max(0, $closing)) ?></td></tr></table></td>
</tr></table>
<?php if (setting('bank_account_number')): ?><div class="box small" style="margin-top: 14px;"><b>Banking details:</b> <?= esc(setting('bank_name')) ?> · <?= esc(setting('bank_account_name')) ?> · Acc <?= esc(setting('bank_account_number')) ?> · Branch <?= esc(setting('bank_branch_code')) ?> · Ref: <?= esc($customer['code']) ?></div><?php endif; ?>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · Customer statement</div>
</body></html>
