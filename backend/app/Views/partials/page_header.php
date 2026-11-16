<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <?php if (!empty($crumbs)): ?>
            <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <?php foreach ($crumbs as $url => $label): ?>
                    <a href="<?= site_url($url) ?>" class="hover:text-crimson-700"><?= esc($label) ?></a><span>/</span>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white"><?= $title ?></h1>
        <?php if (!empty($subtitle)): ?><p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= $subtitle ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="flex flex-wrap items-center gap-2"><?= $actions ?></div><?php endif; ?>
</div>
