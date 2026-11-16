<?php $cols = max(array_map('count', $rows ?: [[]])); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Map columns<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Import ' . esc($type) . ': map columns', 'crumbs' => ['admin/import' => 'Import data']]) ?>
<form method="post" action="<?= site_url('admin/import/run') ?>" class="space-y-6"><?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= esc($type) ?>"><input type="hidden" name="file" value="<?= esc($file) ?>">
    <div class="card"><div class="card-body grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div><label class="label">Skip header rows</label><input type="number" name="skip" value="1" min="0" class="input"></div>
        <?php foreach ($fields as $key => $label): ?>
            <div><label class="label"><?= esc($label) ?></label><select name="map[<?= $key ?>]" class="input"><option value="">— skip —</option><?php for ($i = 0; $i < $cols; $i++): ?><option value="<?= $i ?>" <?= ($guess[$key] ?? null) === $i ? 'selected' : '' ?>><?= esc(($rows[0][$i] ?? '') ?: 'Column ' . chr(65 + $i)) ?></option><?php endfor; ?></select></div>
        <?php endforeach; ?>
    </div></div>
    <div class="card"><div class="card-header"><div class="card-title">Preview</div></div><div class="table-wrap"><table class="table text-xs">
        <tbody><?php foreach (array_slice($rows, 0, 12) as $n => $r): ?><tr class="<?= $n === 0 ? 'bg-slate-50 font-semibold dark:bg-white/5' : '' ?>"><?php for ($i = 0; $i < $cols; $i++): ?><td class="max-w-[180px] truncate"><?= esc($r[$i] ?? '') ?></td><?php endfor; ?></tr><?php endforeach; ?></tbody>
    </table></div></div>
    <div class="flex justify-end gap-2"><a href="<?= site_url('admin/import') ?>" class="btn-light">Cancel</a><button class="btn-primary">Import now</button></div>
</form>
<?= $this->endSection() ?>
