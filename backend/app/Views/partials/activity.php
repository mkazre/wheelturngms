<ol class="relative space-y-4 border-l border-slate-200 pl-5 dark:border-white/10">
    <?php foreach ($activity as $a): ?>
        <li class="relative">
            <span class="absolute -left-[26px] top-1 h-3 w-3 rounded-full border-2 border-white bg-crimson-600 dark:border-navy-900"></span>
            <div class="text-sm"><span class="font-semibold"><?= esc(trim(($a['first_name'] ?? 'System') . ' ' . ($a['last_name'] ?? ''))) ?></span> <span class="text-slate-600 dark:text-slate-300"><?= esc($a['description']) ?></span></div>
            <div class="text-xs text-slate-400"><?= fdatetime($a['created_at']) ?></div>
        </li>
    <?php endforeach; ?>
    <?php if (!$activity): ?><li class="text-sm text-slate-500">No activity recorded yet.</li><?php endif; ?>
</ol>
