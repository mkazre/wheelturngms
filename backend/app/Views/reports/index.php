<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Reports<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Reports &amp; analytics', 'subtitle' => 'Every report exports to branded Excel and PDF.']) ?>

<div class="card mb-8 overflow-hidden">
    <div class="flex flex-col gap-4 bg-gradient-to-r from-navy-900 via-navy-800 to-crimson-800 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10"><?= icon('mail', 'h-6 w-6') ?></div>
            <div>
                <div class="text-lg font-bold">Monday morning performance report</div>
                <p class="text-sm text-slate-300">Every Monday at 07:00, management gets last week's results, month-to-date, and charts by email.<br>Recipients: <b><?= esc(setting('weekly_report_recipients') ?: 'not set') ?></b> · <?= (int) setting('weekly_report_enabled', 1) ? '<span class="text-emerald-300">Enabled</span>' : '<span class="text-amber-300">Disabled</span>' ?><?= setting('weekly_report_last_sent') ? ' · last sent ' . esc(time_ago(setting('weekly_report_last_sent'))) : '' ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <?php if (can('settings')): ?><a href="<?= site_url('settings/notifications') ?>" class="btn bg-white/10 text-white hover:bg-white/20">Configure</a><?php endif; ?>
            <form method="post" action="<?= site_url('reports/weekly/send') ?>"><?= csrf_field() ?><button class="btn bg-white text-navy-900 hover:bg-slate-100"><?= icon('zap', 'h-4 w-4') ?> Send now</button></form>
        </div>
    </div>
</div>

<?php foreach ($catalogue as $group => $items): ?>
    <h2 class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-slate-500"><?= esc($group) ?></h2>
    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($items as $slug => [$title, $desc, $ic]): ?>
            <a href="<?= site_url('reports/' . $slug) ?>" class="card group flex items-start gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-crimson-50 to-navy-50 text-crimson-700 transition group-hover:from-crimson-600 group-hover:to-brand-red group-hover:text-white dark:from-white/5 dark:to-white/10 dark:text-crimson-300"><?= icon($ic, 'h-5 w-5') ?></div>
                <div class="min-w-0 flex-1"><div class="font-semibold text-slate-900 dark:text-white"><?= esc($title) ?></div><div class="text-sm text-slate-500"><?= esc($desc) ?></div></div>
                <span class="text-slate-300 transition group-hover:translate-x-1 group-hover:text-crimson-600"><?= icon('arrow-right', 'h-4 w-4') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>
