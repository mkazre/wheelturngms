<?php /** @var array $cn @var array $items @var array $invoice @var array $customer @var string $logo */ ?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => (int) setting('vat_registered', 1) === 1 ? 'TAX CREDIT NOTE' : 'CREDIT NOTE', 'docNumber' => $cn['number'], 'meta' => [
    'Date' => fdate($cn['issue_date']), 'Original invoice' => esc($invoice['number']), 'Invoice date' => fdate($invoice['issue_date']),
]]) ?>
<?php if ($cn['status'] === 'void'): ?><div class="right"><span class="stamp void">VOID</span></div><?php endif; ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => null, 'job' => null, 'partyLabel' => 'Credited to']) ?>
<div class="box" style="margin-top: 10px;"><div class="label">Reason for credit</div><?= esc($cn['reason']) ?></div>
<?= view('pdf/_items', ['items' => $items, 'doc' => $cn, 'left' => '<div class="small muted">This credit reduces the amount owing on invoice ' . esc($invoice['number']) . '. Remaining balance on that invoice: <b>' . money($invoice['balance_due']) . '</b>.</div>']) ?>
<div class="footer"><?= esc(setting('company_legal_name')) ?><?= setting('company_vat_number') ? ' · VAT ' . esc(setting('company_vat_number')) : '' ?> · Credit note <?= esc($cn['number']) ?></div>
</body></html>
