<?php
/** @var string $label @var string $value @var ?float $delta @var string $icon @var string $tone @var ?string $hint @var bool $inverse */
$inverse = $inverse ?? false;
$tones = [
    'crimson' => 'from-crimson-600 to-brand-red',
    'navy'    => 'from-navy-700 to-navy-500',
    'green'   => 'from-emerald-600 to-emerald-400',
    'amber'   => 'from-amber-500 to-orange-400',
    'violet'  => 'from-violet-600 to-fuchsia-500',
    'sky'     => 'from-sky-600 to-cyan-400',
];
$good = isset($delta) && $delta !== null ? (($delta >= 0) xor $inverse) : null;
?>
<div class="kpi group">
    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-gradient-to-br <?= $tones[$tone ?? 'navy'] ?> opacity-10 transition group-hover:scale-110"></div>
    <div class="flex items-start justify-between">
        <div class="kpi-label"><?= esc($label) ?></div>
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br <?= $tones[$tone ?? 'navy'] ?> text-white shadow-sm"><?= icon($icon ?? 'chart', 'h-[18px] w-[18px]') ?></div>
    </div>
    <div class="kpi-value"><?= $value ?></div>
    <div class="mt-1 flex items-center gap-2 text-xs">
        <?php if (isset($delta)): ?>
            <?php if ($delta === null): ?>
                <span class="badge-gray">new</span>
            <?php else: ?>
                <span class="<?= $good ? 'badge-green' : 'badge-red' ?>"><?= icon($delta >= 0 ? 'arrow-up' : 'arrow-down', 'h-3 w-3') ?><?= number_format(abs($delta), 1) ?>%</span>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($hint)): ?><span class="text-slate-500 dark:text-slate-400"><?= esc($hint) ?></span><?php endif; ?>
    </div>
</div>
