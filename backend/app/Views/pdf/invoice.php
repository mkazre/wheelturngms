<?php
/** @var array $invoice @var array $items @var array $customer @var ?array $vehicle @var array $payments @var string $logo */
$vatOn = (int) setting('vat_registered', 1) === 1;
$s = static fn ($k) => esc(setting($k));
$left = '';
if (!empty($invoice['notes'])) {
    $left .= '<div class="label">Notes</div><div class="small" style="margin-bottom:8px;">' . nl2br(esc($invoice['notes'])) . '</div>';
}
if (setting('bank_account_number')) {
    $left .= '<div class="box"><div class="label">Banking details (EFT)</div><div class="small">' . $s('bank_name') . ' · ' . $s('bank_account_name') . '<br>Account: <b>' . $s('bank_account_number') . '</b> · Branch code: ' . $s('bank_branch_code') . ' · ' . $s('bank_account_type') . '<br>Payment reference: <b>' . esc($invoice['number']) . '</b></div></div>';
}
if (in_array($invoice['status'], ['unpaid', 'partial'], true)) {
    $left .= '<table style="margin-top:8px;"><tr><td style="width:70px;"><img src="' . \App\Libraries\Notify::qr(site_url('pay/' . $invoice['public_token']), 3) . '" style="width:64px;height:64px;"></td><td class="small" style="vertical-align:middle;"><b>Scan to pay online</b><br>Card, Instant EFT &amp; more<br><span style="color:#9b1631">' . esc(site_url('pay/' . $invoice['public_token'])) . '</span></td></tr></table>';
}
$extra = '';
if ((float) ($invoice['amount_credited'] ?? 0) > 0) {
    $extra .= '<tr><td class="muted">Credited</td><td class="right" style="color:#7c3aed">-' . money($invoice['amount_credited']) . '</td></tr>';
}
if ((float) $invoice['amount_paid'] > 0) {
    $extra .= '<tr><td class="muted">Paid</td><td class="right" style="color:#059669">-' . money($invoice['amount_paid']) . '</td></tr>';
}
if ($invoice['status'] !== 'void') {
    $extra .= '<tr class="due"><td>BALANCE DUE</td><td class="right">' . money($invoice['balance_due']) . '</td></tr>';
}
?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => $vatOn ? 'TAX INVOICE' : 'INVOICE', 'docNumber' => $invoice['number'], 'meta' => [
    'Invoice date' => fdate($invoice['issue_date']), 'Due date' => fdate($invoice['due_date']),
    'Order no.' => esc($invoice['order_number']), 'Job card' => $job ? esc($job['number']) : null,
]]) ?>
<?php if ($invoice['status'] === 'paid'): ?><div class="right" style="margin-top:-6px;"><span class="stamp">PAID</span></div><?php elseif ($invoice['status'] === 'void'): ?><div class="right"><span class="stamp void">VOID</span></div><?php endif; ?>
<?= view('pdf/_parties', ['customer' => $customer, 'vehicle' => $vehicle, 'job' => $job]) ?>
<?= view('pdf/_items', ['items' => $items, 'doc' => $invoice, 'left' => $left, 'extraTotals' => $extra]) ?>

<?php if ($payments): ?>
<div class="label" style="margin-top: 14px;">Payments received</div>
<table class="items"><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr></thead><tbody>
<?php foreach ($payments as $p): ?><tr><td><?= esc($p['number']) ?></td><td><?= fdate($p['paid_at']) ?></td><td><?= strtoupper(esc($p['method'])) ?></td><td><?= esc($p['reference'] ?: $p['gateway_txn_id']) ?></td><td class="right"><?= money($p['amount']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<?php if ($invoice['terms']): ?><div style="margin-top: 16px;" class="small muted"><b>Terms:</b> <?= nl2br(esc($invoice['terms'])) ?></div><?php endif; ?>
<div class="footer"><?= $s('invoice_footer') ?> · <?= $s('company_legal_name') ?><?= setting('company_vat_number') ? ' · VAT ' . $s('company_vat_number') : '' ?></div>
</body></html>
