<div class="flex flex-col items-center justify-center px-6 py-16 text-center">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-crimson-50 to-navy-50 text-crimson-700 dark:from-white/5 dark:to-white/10 dark:text-crimson-400"><?= icon($icon ?? 'file', 'h-7 w-7') ?></div>
    <h3 class="text-base font-semibold text-slate-900 dark:text-white"><?= esc($title ?? 'Nothing here yet') ?></h3>
    <?php if (!empty($text)): ?><p class="mt-1 max-w-sm text-sm text-slate-500"><?= esc($text) ?></p><?php endif; ?>
    <?php if (!empty($action)): ?><div class="mt-5"><?= $action ?></div><?php endif; ?>
</div>
