<nav class="card h-fit p-2 lg:sticky lg:top-20">
    <?php foreach ($sections as $k => [$title, $ic]): ?>
        <a href="<?= site_url('settings/' . $k) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium <?= $section === $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5' ?>"><?= icon($ic, 'h-4 w-4') ?> <?= esc($title) ?></a>
    <?php endforeach; ?>
    <a href="<?= site_url('settings/branches') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium <?= $section === 'branches' ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5' ?>"><?= icon('building', 'h-4 w-4') ?> Branches</a>
    <a href="<?= site_url('users') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5"><?= icon('users', 'h-4 w-4') ?> Team &amp; roles</a>
    <a href="<?= site_url('settings/system') ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium <?= $section === 'system' ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5' ?>"><?= icon('settings', 'h-4 w-4') ?> System &amp; cron</a>
</nav>
