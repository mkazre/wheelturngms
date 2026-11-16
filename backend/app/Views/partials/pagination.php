<?php if (($pager['pages'] ?? 1) > 1):
    $q = service('request')->getGet();
    $link = static function (int $p) use ($q) { $q['page'] = $p; return current_url() . '?' . http_build_query($q); };
    $from = ($pager['page'] - 1) * $pager['per_page'] + 1; $to = min($pager['total'], $pager['page'] * $pager['per_page']);
?>
<div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm dark:border-white/10">
    <span class="text-slate-500">Showing <b><?= $from ?></b>–<b><?= $to ?></b> of <b><?= number_format($pager['total']) ?></b></span>
    <div class="flex gap-1">
        <?php if ($pager['page'] > 1): ?><a class="btn-light btn-sm" href="<?= $link($pager['page'] - 1) ?>">← Prev</a><?php endif; ?>
        <span class="btn-ghost btn-sm pointer-events-none"><?= $pager['page'] ?> / <?= $pager['pages'] ?></span>
        <?php if ($pager['page'] < $pager['pages']): ?><a class="btn-light btn-sm" href="<?= $link($pager['page'] + 1) ?>">Next →</a><?php endif; ?>
    </div>
</div>
<?php elseif (isset($pager)): ?>
<div class="border-t border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-white/10"><?= number_format($pager['total']) ?> record<?= $pager['total'] == 1 ? '' : 's' ?></div>
<?php endif; ?>
