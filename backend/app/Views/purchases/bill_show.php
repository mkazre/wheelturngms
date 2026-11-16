<?php $open = in_array($bill['status'], ['unpaid', 'partial'], true); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($bill['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Bill ' . esc($bill['number']) . ' ' . status_badge($open && $bill['due_date'] < date('Y-m-d') ? 'overdue' : $bill['status'], 'invoice'),
    'subtitle' => esc($supplier['name']) . ($bill['supplier_invoice'] ? ' · supplier invoice ' . esc($bill['supplier_invoice']) : '') . ($po ? ' · from <a class="font-semibold text-crimson-700 dark:text-crimson-400" href="' . site_url('purchase-orders/' . $po['id']) . '">' . esc($po['number']) . '</a>' : ''),
    'crumbs' => ['bills' => 'Supplier bills'],
    'actions' => ((float) $bill['amount_paid'] == 0 && $bill['status'] !== 'void' ? '<a href="' . site_url('bills/' . $bill['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>' : '')]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="table-wrap"><table class="table">
            <thead><tr><th>Item</th><th>Posted to</th><th class="text-right">Qty</th><th class="text-right">Unit cost</th><th class="text-right">VAT</th><th class="text-right">Amount (excl.)</th></tr></thead>
            <tbody><?php foreach ($items as $it): ?><tr><td class="whitespace-normal"><?= esc($it['description']) ?></td><td class="text-xs text-slate-500"><?= $it['account_name'] ? esc($it['account_code'] . ' ' . $it['account_name']) : ($it['product_id'] ? 'Inventory / cost of sales' : 'Cost of sales') ?></td><td class="text-right"><?= (float) $it['quantity'] ?></td><td class="text-right"><?= money($it['unit_price']) ?></td><td class="text-right text-slate-500"><?= money($it['line_vat']) ?></td><td class="text-right font-semibold"><?= money($it['line_subtotal'] - $it['line_discount']) ?></td></tr><?php endforeach; ?></tbody>
            <tfoot>
                <tr><td colspan="5" class="px-4 py-1.5 text-right text-slate-500">Input VAT</td><td class="px-4 py-1.5 text-right"><?= money($bill['vat_total']) ?></td></tr>
                <tr><td colspan="5" class="px-4 py-1.5 text-right font-bold">Total</td><td class="px-4 py-1.5 text-right font-bold"><?= money($bill['total']) ?></td></tr>
                <tr><td colspan="5" class="px-4 py-1.5 text-right text-slate-500">Paid</td><td class="px-4 py-1.5 text-right text-emerald-600">-<?= money($bill['amount_paid']) ?></td></tr>
                <tr><td colspan="5" class="px-4 py-2 text-right font-bold">Balance due</td><td class="px-4 py-2 text-right text-lg font-bold"><?= money($bill['balance_due']) ?></td></tr>
            </tfoot>
        </table></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Payments made</div></div>
            <div class="table-wrap"><table class="table"><thead><tr><th>No.</th><th>Date</th><th>From</th><th>Reference</th><th class="text-right">Amount</th></tr></thead><tbody>
                <?php foreach ($payments as $p): ?><tr><td class="font-semibold"><?= esc($p['number']) ?></td><td><?= fdate($p['paid_at']) ?></td><td><?= esc($p['account_name']) ?></td><td class="text-slate-500"><?= esc($p['reference']) ?></td><td class="text-right font-semibold"><?= money($p['amount']) ?></td></tr><?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="5" class="py-6 text-center text-slate-500">No payments yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="space-y-6">
        <?php if ($open): ?>
        <form method="post" action="<?= site_url('bills/' . $bill['id'] . '/pay') ?>" class="card overflow-hidden"><?= csrf_field() ?>
            <div class="bg-gradient-to-br from-navy-900 to-navy-700 p-5 text-white"><div class="text-xs uppercase tracking-wider text-slate-300">Balance due <?= fdate($bill['due_date']) ?></div><div class="text-3xl font-extrabold"><?= money($bill['balance_due']) ?></div></div>
            <div class="card-body space-y-3">
                <div class="card-title">Pay supplier</div>
                <div class="grid grid-cols-2 gap-3"><div><label class="label">Amount</label><input name="amount" type="number" step="0.01" value="<?= (float) $bill['balance_due'] ?>" max="<?= (float) $bill['balance_due'] ?>" class="input"></div>
                    <div><label class="label">Date</label><input name="paid_at" type="date" value="<?= date('Y-m-d') ?>" class="input"></div></div>
                <div><label class="label">Paid from</label><select name="paid_from_id" class="input"><?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>"><?= esc($b['code'] . ' ' . $b['name']) ?></option><?php endforeach; ?></select></div>
                <div class="grid grid-cols-2 gap-3"><div><label class="label">Method</label><select name="method" class="input"><option value="eft">EFT</option><option value="card">Card</option><option value="cash">Cash</option><option value="debit_order">Debit order</option></select></div>
                    <div><label class="label">Reference</label><input name="reference" class="input"></div></div>
                <button class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Record payment</button>
            </div>
        </form>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Supplier invoice &amp; documents</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'bill', 'entityId' => $bill['id']]) ?></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Activity</div></div><div class="card-body"><?= view('partials/activity', ['activity' => $activity]) ?></div></div>
        <?php if ($bill['status'] !== 'void' && (float) $bill['amount_paid'] == 0): ?><form method="post" action="<?= site_url('bills/' . $bill['id'] . '/void') ?>" data-confirm="Void this bill? Stock and the ledger will be reversed." class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Void bill</button></form><?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
