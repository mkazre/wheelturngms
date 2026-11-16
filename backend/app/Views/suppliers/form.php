<?php $isEdit = !empty($s['id']); $val = static fn ($k, $d = '') => esc(old($k, $s[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? esc($s['name']) : 'New supplier' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? esc($s['name']) : 'New supplier', 'crumbs' => ['suppliers' => 'Suppliers']]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <form method="post" action="<?= site_url($isEdit ? 'suppliers/' . $s['id'] . '/save' : 'suppliers/save') ?>" class="card xl:col-span-2"><?= csrf_field() ?>
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Name *</label><input name="name" required value="<?= $val('name') ?>" class="input"></div>
            <div><label class="label">Contact person</label><input name="contact_person" value="<?= $val('contact_person') ?>" class="input"></div>
            <div><label class="label">Phone</label><input name="phone" value="<?= $val('phone') ?>" class="input"></div>
            <div><label class="label">Email</label><input name="email" type="email" value="<?= $val('email') ?>" class="input"></div>
            <div><label class="label">VAT number</label><input name="vat_number" value="<?= $val('vat_number') ?>" class="input"></div>
            <div><label class="label">Our account no.</label><input name="account_number" value="<?= $val('account_number') ?>" class="input"></div>
            <div><label class="label">Payment terms (days)</label><input name="payment_terms_days" type="number" value="<?= $val('payment_terms_days', 30) ?>" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Address</label><textarea name="address" rows="2" class="input"><?= $val('address') ?></textarea></div>
            <div class="sm:col-span-2"><label class="label">Notes</label><textarea name="notes" rows="2" class="input"><?= $val('notes') ?></textarea></div>
        </div>
        <div class="flex gap-2 border-t border-slate-100 p-5 dark:border-white/10"><button class="btn-primary"><?= icon('check', 'h-4 w-4') ?> Save supplier</button><a href="<?= site_url('suppliers') ?>" class="btn-light">Cancel</a></div>
    </form>
    <?php if ($isEdit): ?>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Documents</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'supplier', 'entityId' => $s['id']]) ?></div></div>
        <form method="post" action="<?= site_url('suppliers/' . $s['id'] . '/delete') ?>" data-confirm="Archive this supplier?" class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Archive supplier</button></form>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
