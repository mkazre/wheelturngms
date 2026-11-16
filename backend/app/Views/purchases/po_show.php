<?php $open = in_array($po['status'], ['draft', 'sent'], true); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($po['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Purchase order ' . esc($po['number']), 'subtitle' => esc($supplier['name']) . ($job ? ' · for job card <a class="font-semibold text-crimson-700 dark:text-crimson-400" href="' . site_url('job-cards/' . $job['id']) . '">' . esc($job['number']) . '</a>' : ''), 'crumbs' => ['purchase-orders' => 'Purchase orders'],
    'actions' => '<a href="' . site_url('purchase-orders/' . $po['id'] . '/pdf') . '" target="_blank" class="btn-light">' . icon('printer', 'h-4 w-4') . ' PDF</a>' . ($open ? '<a href="' . site_url('purchase-orders/' . $po['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>' : '')]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit cost</th><th class="text-right">Amount (excl.)</th></tr></thead>
            <tbody><?php foreach ($items as $it): ?><tr><td class="whitespace-normal"><?= esc($it['description']) ?> <span class="text-xs text-slate-500"><?= esc($it['sku']) ?></span></td><td class="text-right"><?= (float) $it['quantity'] ?></td><td class="text-right"><?= money($it['unit_price']) ?></td><td class="text-right font-semibold"><?= money($it['line_subtotal'] - $it['line_discount']) ?></td></tr><?php endforeach; ?></tbody>
            <tfoot><tr><td colspan="3" class="px-4 py-2 text-right text-slate-500">VAT</td><td class="px-4 py-2 text-right"><?= money($po['vat_total']) ?></td></tr><tr><td colspan="3" class="px-4 py-2 text-right font-bold">Total</td><td class="px-4 py-2 text-right text-lg font-bold"><?= money($po['total']) ?></td></tr></tfoot>
        </table></div>
    </div>
    <div class="space-y-6">
        <?php if ($bill): ?>
            <a href="<?= site_url('bills/' . $bill['id']) ?>" class="card block p-5 hover:shadow-lg"><div class="text-xs font-semibold uppercase text-emerald-600">Received &amp; billed</div><div class="mt-1 text-lg font-bold"><?= esc($bill['number']) ?></div><div class="text-sm text-slate-500"><?= ucfirst($bill['status']) ?> · balance <?= money($bill['balance_due']) ?></div></a>
        <?php elseif ($open): ?>
            <form method="post" action="<?= site_url('purchase-orders/' . $po['id'] . '/receive') ?>" class="card"><?= csrf_field() ?>
                <div class="card-header"><div class="card-title">Goods received</div></div>
                <div class="card-body space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">When the parts arrive, capture the supplier's invoice. Stock is updated at weighted-average cost and the bill is posted to payables.</p>
                    <div><label class="label">Supplier invoice no.</label><input name="supplier_invoice" class="input"></div>
                    <div><label class="label">Invoice date</label><input type="date" name="bill_date" value="<?= date('Y-m-d') ?>" class="input"></div>
                    <button class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Receive &amp; create bill</button>
                </div>
            </form>
            <form method="post" action="<?= site_url('purchase-orders/' . $po['id'] . '/email') ?>" class="card flex gap-2 p-4"><?= csrf_field() ?><input name="to" type="email" value="<?= esc($supplier['email']) ?>" placeholder="supplier@email" class="input flex-1"><button class="btn-dark"><?= icon('mail', 'h-4 w-4') ?> Send</button></form>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Activity</div></div><div class="card-body"><?= view('partials/activity', ['activity' => $activity]) ?></div></div>
        <?php if ($open): ?><form method="post" action="<?= site_url('purchase-orders/' . $po['id'] . '/cancel') ?>" data-confirm="Cancel this purchase order?" class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Cancel PO</button></form><?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
