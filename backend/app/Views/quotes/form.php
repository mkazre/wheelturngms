<?php $isEdit = !empty($q['id']); $val = static fn ($k, $d = '') => esc(old($k, $q[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit ' . esc($q['number']) : 'New quote' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit quote ' . esc($q['number']) : 'New quote', 'crumbs' => ['quotes' => 'Quotes']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'quotes/' . $q['id'] . '/save' : 'quotes/save') ?>" class="grid gap-6 xl:grid-cols-3">
    <?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <?= view('partials/customer_vehicle', ['customer' => $customer, 'vehicle' => $vehicle, 'returnTo' => 'quotes/new', 'vehicleRequired' => false]) ?>
        </div></div>
        <?= view('partials/line_items_editor', ['lines' => $lines]) ?>
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div><label class="label">Notes to customer</label><textarea name="notes" rows="4" class="input" placeholder="Scope of work, assumptions, warranty…"><?= $val('notes') ?></textarea></div>
            <div><label class="label">Terms</label><textarea name="terms" rows="4" class="input"><?= $val('terms') ?></textarea></div>
        </div></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Quote date</label><input type="date" name="issue_date" value="<?= $val('issue_date') ?>" class="input" required></div>
            <div><label class="label">Valid until</label><input type="date" name="expiry_date" value="<?= $val('expiry_date') ?>" class="input" required></div>
        </div></div>
        <div class="sticky top-20 space-y-2">
            <button class="btn-dark w-full py-3"><?= icon('check', 'h-4 w-4') ?> Save quote</button>
            <button name="send" value="1" class="btn-primary w-full py-3"><?= icon('mail', 'h-4 w-4') ?> Save &amp; email to customer</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
