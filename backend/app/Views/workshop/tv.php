<?php $statuses = job_statuses(); $show = ['checked_in', 'in_progress', 'awaiting_parts', 'awaiting_approval', 'quality_check', 'completed']; ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Workshop display · <?= esc(setting('company_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="h-full overflow-hidden bg-navy-950 text-white">
<div class="flex h-full flex-col p-6">
    <header class="mb-5 flex items-center justify-between">
        <div class="flex items-center gap-5">
            <div class="rounded-2xl bg-white px-4 py-2"><img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="" class="h-12"></div>
            <div><div class="text-2xl font-extrabold">Workshop live</div><div class="text-sm text-slate-400">Updated <?= date('H:i') ?> · refreshes every minute</div></div>
        </div>
        <div class="flex items-center gap-8 text-right">
            <div><div class="text-4xl font-extrabold"><?= array_sum(array_map('count', array_intersect_key($columns, array_flip(array_diff($show, ['completed']))))) ?></div><div class="text-xs uppercase tracking-widest text-slate-400">In workshop</div></div>
            <div><div class="text-4xl font-extrabold text-emerald-400"><?= $completedToday ?></div><div class="text-xs uppercase tracking-widest text-slate-400">Done today</div></div>
            <div class="text-5xl font-extrabold tabular-nums" id="clock"><?= date('H:i') ?></div>
        </div>
    </header>
    <div class="grid flex-1 grid-cols-6 gap-4 overflow-hidden">
        <?php foreach ($show as $st): $s = $statuses[$st]; ?>
            <section class="flex flex-col overflow-hidden rounded-3xl bg-white/[.04] ring-1 ring-white/10">
                <div class="flex items-center justify-between px-4 py-3" style="border-top: 4px solid <?= $s['hex'] ?>; border-radius: 1.5rem 1.5rem 0 0;">
                    <span class="text-lg font-bold"><?= $s['label'] ?></span><span class="rounded-full bg-white/10 px-2.5 py-0.5 text-lg font-extrabold"><?= count($columns[$st] ?? []) ?></span>
                </div>
                <div class="flex-1 space-y-3 overflow-hidden px-3 pb-3">
                    <?php foreach (array_slice($columns[$st] ?? [], 0, 6) as $j): $late = $j['promised_at'] && strtotime($j['promised_at']) < time() && $st !== 'completed'; ?>
                        <div class="rounded-2xl bg-white/[.06] p-3 <?= $late ? 'ring-2 ring-red-500' : '' ?>">
                            <div class="flex items-center justify-between"><span class="rounded-md border-2 border-black bg-amber-300 px-2 font-mono text-base font-extrabold text-slate-900"><?= esc($j['registration']) ?></span><?php if ($j['running']): ?><span class="h-3 w-3 animate-pulse rounded-full bg-emerald-400"></span><?php endif; ?></div>
                            <div class="mt-2 truncate text-base font-bold"><?= esc(trim($j['make'] . ' ' . $j['model'])) ?></div>
                            <div class="flex items-center justify-between text-sm text-slate-400"><span><?= esc(implode(' ', $j['mechs'])) ?: '—' ?></span><span class="<?= $late ? 'font-bold text-red-400' : '' ?>"><?= $j['promised_at'] ? date('H:i', strtotime($j['promised_at'])) : esc($j['bay'] ?? '') ?></span></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($columns[$st] ?? []) > 6): ?><div class="text-center text-sm text-slate-400">+<?= count($columns[$st]) - 6 ?> more</div><?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<script>setInterval(() => { const d = new Date(); document.getElementById('clock').textContent = d.toTimeString().slice(0, 5); }, 10000);</script>
</body>
</html>
