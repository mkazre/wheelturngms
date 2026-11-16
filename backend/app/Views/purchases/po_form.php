<?php $isEdit = !empty($po['id']); $val = static fn ($k, $d = '') => esc(old($k, $po[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit ' . esc($po['number']) : 'New purchase order' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit ' . esc($po['number']) : 'New purchase order', 'subtitle' => 'Prices are your cost from the supplier, excl. VAT.', 'crumbs' => ['purchase-orders' => 'Purchase orders']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'purchase-orders/' . $po['id'] . '/save' : 'purchase-orders/save') ?>" class="grid gap-6 xl:grid-cols-3"><?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div><label class="label">Supplier *</label><select name="supplier_id" data-select required class="input"><option value="">Select…</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($po['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; ?></select>
                <a href="<?= site_url('suppliers/new') ?>" class="mt-1 inline-block text-xs font-semibold text-crimson-700 dark:text-crimson-400">+ New supplier</a></div>
            <div><label class="label">For job card (optional)</label><select name="job_card_id" data-select class="input"><option value="">— Stock order —</option><?php foreach ($jobs as $j): ?><option value="<?= $j['id'] ?>" <?= (int) ($po['job_card_id'] ?? 0) === (int) $j['id'] ? 'selected' : '' ?>><?= esc($j['number'] . ' · ' . $j['registration']) ?></option><?php endforeach; ?></select></div>
        </div></div>
        <?= view('partials/line_items_editor', ['lines' => $lines, 'mode' => 'purchase']) ?>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Order date</label><input type="date" name="order_date" value="<?= $val('order_date') ?>" class="input" required></div>
            <div><label class="label">Required by</label><input type="date" name="expected_date" value="<?= $val('expected_date') ?>" class="input"></div>
            <div><label class="label">Notes to supplier</label><textarea name="notes" rows="3" class="input" placeholder="Delivery instructions, vehicle details…"><?= $val('notes') ?></textarea></div>
        </div></div>
        <div class="sticky top-20 space-y-2">
            <button class="btn-dark w-full py-3"><?= icon('check', 'h-4 w-4') ?> Save PO</button>
            <button name="send" value="1" class="btn-primary w-full py-3"><?= icon('mail', 'h-4 w-4') ?> Save &amp; email supplier</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
