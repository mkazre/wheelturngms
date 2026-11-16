<?php $isEdit = !empty($c['id']); $val = static fn ($k, $d = '') => esc(old($k, $c[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Service package<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? esc($c['name']) : 'New service package', 'crumbs' => ['canned-jobs' => 'Service packages']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'canned-jobs/' . $c['id'] . '/save' : 'canned-jobs/save') ?>" class="grid gap-6 xl:grid-cols-3"><?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2"><?= view('partials/line_items_editor', ['lines' => $lines, 'mode' => 'package']) ?></div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Package name *</label><input name="name" value="<?= $val('name') ?>" required class="input" placeholder="e.g. Minor service – petrol"></div>
            <div><label class="label">Job type</label><select name="job_type" class="input"><option value="">—</option><?php foreach (\App\Controllers\JobCards::JOB_TYPES as $t): ?><option <?= ($c['job_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Standard time (hours)</label><input name="labour_hours" type="number" step="0.1" value="<?= $val('labour_hours', 1) ?>" class="input"><p class="mt-1 text-xs text-slate-500">Used for scheduling and technician efficiency.</p></div>
            <div><label class="label">Description (shown to customer)</label><textarea name="description" rows="3" class="input"><?= $val('description') ?></textarea></div>
            <div><label class="label">Display order</label><input name="sort_order" type="number" value="<?= $val('sort_order', 0) ?>" class="input"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($c['is_active']) ? 'checked' : '' ?> class="rounded text-crimson-600"> Active</label>
        </div></div>
        <button class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Save package</button>
        <?php if ($isEdit): ?><button form="del" class="btn-ghost btn-sm w-full text-red-600">Delete package</button><?php endif; ?>
    </div>
</form>
<?php if ($isEdit): ?><form id="del" method="post" action="<?= site_url('canned-jobs/' . $c['id'] . '/delete') ?>" data-confirm="Delete this package?"><?= csrf_field() ?></form><?php endif; ?>
<?= $this->endSection() ?>
