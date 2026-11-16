<?php $isEdit = !empty($inv['id']); $val = static fn ($k, $d = '') => esc(old($k, $inv[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit ' . esc($inv['number']) : 'New invoice' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit invoice ' . esc($inv['number']) : 'New tax invoice', 'crumbs' => ['invoices' => 'Invoices']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'invoices/' . $inv['id'] . '/save' : 'invoices/save') ?>" class="grid gap-6 xl:grid-cols-3">
    <?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <?= view('partials/customer_vehicle', ['customer' => $customer, 'vehicle' => $vehicle, 'returnTo' => 'invoices/new', 'vehicleRequired' => false]) ?>
        </div></div>
        <?= view('partials/line_items_editor', ['lines' => $lines]) ?>
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div><label class="label">Notes (printed)</label><textarea name="notes" rows="4" class="input"><?= $val('notes') ?></textarea></div>
            <div><label class="label">Terms</label><textarea name="terms" rows="4" class="input"><?= $val('terms') ?></textarea></div>
        </div></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Invoice date</label><input type="date" name="issue_date" value="<?= $val('issue_date') ?>" required class="input"></div>
            <div><label class="label">Due date</label><input type="date" name="due_date" value="<?= $val('due_date') ?>" required class="input"></div>
            <div><label class="label">Customer order / PO no.</label><input name="order_number" value="<?= $val('order_number') ?>" class="input"></div>
        </div></div>
        <div class="sticky top-20 space-y-2">
            <button class="btn-dark w-full py-3"><?= icon('check', 'h-4 w-4') ?> <?= $isEdit && ($inv['status'] ?? '') !== 'draft' ? 'Save invoice' : 'Issue invoice' ?></button>
            <button name="send" value="1" class="btn-primary w-full py-3"><?= icon('mail', 'h-4 w-4') ?> Issue &amp; email with pay link</button>
            <?php if (!$isEdit || ($inv['status'] ?? '') === 'draft'): ?><button name="draft" value="1" class="btn-light w-full">Save as draft</button><?php endif; ?>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
