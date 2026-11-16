<?php /** @var array $payment @var array $customer @var ?array $invoice @var string $logo */ ?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => 'PAYMENT RECEIPT', 'docNumber' => $payment['number'], 'meta' => ['Date' => fdatetime($payment['paid_at']), 'Method' => strtoupper(esc($payment['method'])), 'Reference' => esc($payment['reference'] ?: $payment['gateway_txn_id'])]]) ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => null, 'job' => null, 'partyLabel' => 'Received from']) ?>
<div style="margin-top: 24px; text-align: center;">
    <div class="label">Amount received</div>
    <div style="font-size: 30px; font-weight: bold; color: #0b0d2e;"><?= money($payment['amount']) ?></div>
    <?php if ($payment['status'] !== 'completed'): ?><span class="stamp void"><?= strtoupper(esc($payment['status'])) ?></span><?php else: ?><span class="stamp">RECEIVED</span><?php endif; ?>
</div>
<?php if ($invoice): ?>
<table class="items" style="margin-top: 24px;">
    <thead><tr><th>Applied to invoice</th><th>Invoice date</th><th class="right">Invoice total</th><th class="right">Balance after payment</th></tr></thead>
    <tbody><tr><td class="bold"><?= esc($invoice['number']) ?></td><td><?= fdate($invoice['issue_date']) ?></td><td class="right"><?= money($invoice['total']) ?></td><td class="right bold"><?= money($invoice['balance_due']) ?></td></tr></tbody>
</table>
<?php endif; ?>
<p class="center muted" style="margin-top: 30px;">Thank you for your payment.</p>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · This receipt is not a tax invoice.</div>
</body></html>
