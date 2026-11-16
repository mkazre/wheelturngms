<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($v['registration']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $actions = '<a href="' . site_url('vehicles/' . $v['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>'
    . '<a href="' . site_url('quotes/new?customer_id=' . $v['customer_id'] . '&vehicle_id=' . $v['id']) . '" class="btn-light">' . icon('quote', 'h-4 w-4') . ' Quote</a>'
    . '<a href="' . site_url('job-cards/new?customer_id=' . $v['customer_id'] . '&vehicle_id=' . $v['id']) . '" class="btn-primary">' . icon('wrench', 'h-4 w-4') . ' New job card</a>'; ?>
<?= view('partials/page_header', ['title' => esc(trim($v['year'] . ' ' . $v['make'] . ' ' . $v['model'])), 'subtitle' => 'Owner: <a class="font-semibold text-crimson-700 hover:underline dark:text-crimson-400" href="' . site_url('customers/' . $customer['id']) . '">' . esc(customer_name($customer)) . '</a>', 'crumbs' => ['vehicles' => 'Vehicles'], 'actions' => $actions]) ?>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="relative bg-gradient-to-br from-navy-900 to-navy-700 p-6 text-white">
                <div class="absolute right-4 top-4 opacity-20"><?= icon('car', 'h-24 w-24') ?></div>
                <span class="inline-block rounded-md border-2 border-slate-900 bg-amber-300 px-3 py-1 font-mono text-lg font-extrabold text-slate-900 shadow"><?= esc($v['registration']) ?></span>
                <div class="mt-4 text-lg font-bold"><?= esc($v['variant'] ?: $v['make'] . ' ' . $v['model']) ?></div>
                <div class="font-mono text-xs text-slate-300">VIN <?= esc($v['vin'] ?: '—') ?></div>
            </div>
            <dl class="card-body grid grid-cols-2 gap-4 text-sm">
                <?php foreach (['Odometer' => $v['odometer'] ? number_format($v['odometer']) . ' km' : null, 'Colour' => $v['colour'], 'Fuel' => $v['fuel_type'], 'Gearbox' => $v['transmission'], 'Engine' => trim($v['engine_size'] . ' ' . $v['engine_number']), 'Licence expiry' => $v['licence_expiry'] ? fdate($v['licence_expiry']) : null, 'Next service' => $v['next_service_date'] ? fdate($v['next_service_date']) : null, 'Service at' => $v['next_service_km'] ? number_format($v['next_service_km']) . ' km' : null] as $k => $val): ?>
                    <div><dt class="text-xs text-slate-500"><?= $k ?></dt><dd class="font-semibold"><?= esc($val ?: '—') ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <?= view('partials/kpi', ['label' => 'Visits', 'value' => count($jobs), 'icon' => 'wrench', 'tone' => 'navy']) ?>
            <?= view('partials/kpi', ['label' => 'Total spent', 'value' => money_short($spent), 'icon' => 'wallet', 'tone' => 'crimson']) ?>
        </div>
        <?php if ($aiEnabled): ?>
        <div class="card" x-data="{ busy: false, html: '', async go() { this.busy = true; try { const r = await wtPost(WT_BASE + 'assistant/upsell/<?= $v['id'] ?>', {}); this.html = r.html; } catch (e) { toast(e.message, 'error'); } this.busy = false; } }">
            <div class="card-header"><div class="card-title flex items-center gap-2"><?= icon('zap', 'h-4 w-4 text-crimson-600') ?> AI service suggestions</div><button @click="go()" :disabled="busy" class="btn-light btn-sm"><span x-show="!busy">Analyse</span><span x-show="busy" x-cloak>Analysing…</span></button></div>
            <div class="card-body text-sm"><p x-show="!html" class="text-slate-500">Checks this vehicle's age, mileage and history against typical service intervals to find work it is likely due for.</p><div x-show="html" x-cloak x-html="html" class="leading-relaxed"></div></div>
        </div>
        <?php endif; ?>
        <?php if ($v['vin']): ?>
        <div class="card"><div class="card-header"><div class="card-title">Find OEM parts by VIN</div></div>
            <div class="card-body space-y-2 text-sm" x-data="{ copied: false }">
                <button type="button" @click="navigator.clipboard.writeText('<?= esc($v['vin'], 'js') ?>'); copied = true; setTimeout(() => copied = false, 1500)" class="btn-light btn-sm w-full font-mono"><span x-text="copied ? 'VIN copied' : 'Copy VIN <?= esc($v['vin'], 'js') ?>'"></span></button>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (['PartSouq (OEM diagrams)' => 'https://partsouq.com/en/search/all?q=' . rawurlencode($v['vin']), '7zap (OEM catalogue)' => 'https://7zap.com/en/search/?q=' . rawurlencode($v['vin']), 'Goldwagen' => 'https://www.goldwagen.com/', 'Midas' => 'https://www.midas.co.za/'] as $l => $u): ?>
                        <a href="<?= esc($u, 'attr') ?>" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold hover:border-crimson-300 dark:border-white/10"><?= $l ?> ↗</a>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-slate-500">Opens external catalogues with exploded diagrams and OEM part numbers.</p>
            </div>
        </div>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Ownership</div></div>
            <div class="card-body space-y-3 text-sm">
                <?php foreach ($owners as $o): ?><div class="flex justify-between"><span><?= esc(customer_name($o)) ?></span><span class="text-xs text-slate-500"><?= fdate($o['from_date']) ?> – <?= $o['to_date'] ? fdate($o['to_date']) : 'now' ?></span></div><?php endforeach; ?>
                <details><summary class="cursor-pointer text-xs font-semibold text-crimson-700">Transfer to a new owner</summary>
                    <form method="post" action="<?= site_url('vehicles/' . $v['id'] . '/transfer') ?>" class="mt-2 space-y-2"><?= csrf_field() ?>
                        <select name="customer_id" data-select data-remote="<?= site_url('customers/lookup') ?>" required class="input"></select>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="input"><button class="btn-dark btn-sm w-full">Transfer ownership</button>
                    </form>
                </details>
            </div>
        </div>
        <?php if ($advisories): ?>
        <div class="card"><div class="card-header"><div class="card-title">Outstanding advisories</div><span class="badge-amber"><?= count($advisories) ?></span></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                <?php foreach ($advisories as $a): ?>
                    <li class="flex items-start gap-3 px-5 py-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $a['status'] === 'urgent' ? 'bg-red-500' : 'bg-amber-400' ?>"></span>
                        <div class="flex-1"><div class="font-semibold"><?= esc($a['item']) ?></div><div class="text-xs text-slate-500"><?= esc($a['note'] ?: $a['section']) ?> · <?= esc($a['number']) ?>, <?= fdate($a['job_date']) ?></div></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-header"><div class="card-title">Service history</div><span class="text-xs text-slate-500"><?= count($jobs) ?> visits</span></div>
            <div class="card-body">
                <ol class="relative space-y-6 border-l-2 border-slate-100 pl-6 dark:border-white/10">
                    <?php foreach ($jobs as $j): ?>
                        <li class="relative">
                            <span class="absolute -left-[33px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-white ring-4 ring-crimson-100 dark:bg-navy-900 dark:ring-crimson-500/20"><span class="h-2 w-2 rounded-full bg-crimson-600"></span></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="<?= site_url('job-cards/' . $j['id']) ?>" class="font-semibold hover:text-crimson-700"><?= esc($j['number']) ?></a>
                                <?= status_badge($j['status']) ?>
                                <span class="text-xs text-slate-500"><?= fdate($j['created_at']) ?><?= $j['odometer_in'] ? ' · ' . number_format($j['odometer_in']) . ' km' : '' ?></span>
                                <span class="ml-auto text-sm font-semibold"><?= money($j['total']) ?></span>
                            </div>
                            <?php if ($j['customer_complaint']): ?><p class="mt-1 text-sm text-slate-600 dark:text-slate-300"><?= esc($j['customer_complaint']) ?></p><?php endif; ?>
                            <?php if (!empty($workByJob[$j['id']])): ?>
                                <div class="mt-2 flex flex-wrap gap-1.5"><?php foreach (array_slice($workByJob[$j['id']], 0, 8) as $w): ?><span class="<?= $w['item_type'] === 'labour' ? 'badge-navy' : 'badge-gray' ?>"><?= esc(mb_strimwidth($w['description'], 0, 40, '…')) ?></span><?php endforeach; ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$jobs): ?><li class="text-sm text-slate-500">No visits yet.</li><?php endif; ?>
                </ol>
            </div>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Photos &amp; documents</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'vehicle', 'entityId' => $v['id'], 'defaultCategory' => 'photo']) ?></div></div>
        <div class="flex justify-end"><form method="post" action="<?= site_url('vehicles/' . $v['id'] . '/delete') ?>" data-confirm="Archive this vehicle?"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600"><?= icon('trash', 'h-3.5 w-3.5') ?> Archive vehicle</button></form></div>
    </div>
</div>
<?= $this->endSection() ?>
