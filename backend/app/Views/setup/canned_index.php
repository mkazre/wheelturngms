<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Service Packages<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Service packages', 'subtitle' => 'Pre-built jobs (labour + parts + standard time). Insert them into job cards and quotes in one click.', 'actions' => '<a href="' . site_url('canned-jobs/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New package</a>']) ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($rows as $r): ?>
        <a href="<?= site_url('canned-jobs/' . $r['id'] . '/edit') ?>" class="card p-5 transition hover:shadow-lg <?= $r['is_active'] ? '' : 'opacity-50' ?>">
            <div class="flex items-start justify-between"><div class="font-semibold text-slate-900 dark:text-white"><?= esc($r['name']) ?></div><span class="badge-navy"><?= (float) $r['labour_hours'] ?> h</span></div>
            <div class="mt-1 text-xs text-slate-500"><?= esc($r['job_type'] ?: 'General') ?> · <?= (int) $r['lines'] ?> lines</div>
            <?php if ($r['description']): ?><p class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-300"><?= esc($r['description']) ?></p><?php endif; ?>
            <div class="mt-3 text-lg font-bold"><?= money($r['price']) ?> <span class="text-xs font-normal text-slate-500">excl. VAT</span></div>
        </a>
    <?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="card"><?= view('partials/empty', ['icon' => 'wrench', 'title' => 'No packages yet', 'text' => 'Create packages like "Minor service" or "Front brake pads" to quote faster.']) ?></div><?php endif; ?>
<?= $this->endSection() ?>
