<?php $isEdit = !empty($bill['id']); $val = static fn ($k, $d = '') => esc(old($k, $bill[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit bill' : 'Capture supplier bill' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit bill ' . esc($bill['number']) : 'Capture supplier bill', 'subtitle' => 'Stocked parts go into inventory automatically. Pick a ledger account for anything else (e.g. sublet work, rent, tools).', 'crumbs' => ['bills' => 'Supplier bills']]) ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isEdit ? 'bills/' . $bill['id'] . '/save' : 'bills/save') ?>" class="grid gap-6 xl:grid-cols-3"><?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div><label class="label">Supplier *</label><select name="supplier_id" data-select required class="input"><option value="">Select…</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($bill['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Supplier invoice no.</label><input name="supplier_invoice" value="<?= $val('supplier_invoice') ?>" class="input"></div>
        </div></div>
        <?= view('partials/line_items_editor', ['lines' => $lines, 'mode' => 'purchase', 'accounts' => $accounts]) ?>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Invoice date</label><input type="date" name="bill_date" value="<?= $val('bill_date') ?>" class="input" required></div>
            <div><label class="label">Due date</label><input type="date" name="due_date" value="<?= $val('due_date') ?>" class="input" required></div>
            <div><label class="label">Notes</label><textarea name="notes" rows="2" class="input"><?= $val('notes') ?></textarea></div>
            <div><label class="label">Attach supplier invoice (PDF / photo)</label><input type="file" name="document" accept="image/*,.pdf" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white"></div>
        </div></div>
        <button class="btn-primary w-full py-3"><?= icon('check', 'h-4 w-4') ?> Save bill</button>
    </div>
</form>
<?= $this->endSection() ?>
