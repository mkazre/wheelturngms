<?php
$statuses = job_statuses();
$flow = ['booked', 'checked_in', 'in_progress', 'quality_check', 'completed', 'invoiced', 'collected'];
$curIdx = array_search($job['status'], $flow, true);
$isMech = session('role') === 'mechanic';
$sections = [];
foreach ($inspections as $it) {
    $sections[$it['section'] ?: 'General'][] = $it;
}
$insp = ['ok' => 0, 'advisory' => 0, 'urgent' => 0, 'pending' => 0];
foreach ($inspections as $it) {
    $insp[in_array($it['status'], ['ok', 'advisory', 'urgent'], true) ? $it['status'] : 'pending']++;
}
$totalMins = array_sum(array_column($timeLogs, 'minutes'));
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($job['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <nav class="mb-1 text-xs font-medium text-slate-500"><a href="<?= site_url('job-cards') ?>" class="hover:text-crimson-700">Job cards</a> /</nav>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"><?= esc($job['number']) ?></h1>
            <?= status_badge($job['status']) ?>
            <?php if ($job['priority'] === 'urgent' || $job['priority'] === 'high'): ?><span class="badge-red"><?= ucfirst($job['priority']) ?> priority</span><?php endif; ?>
        </div>
        <p class="mt-1 text-sm text-slate-500">
            <a href="<?= site_url('vehicles/' . $vehicle['id']) ?>" class="font-semibold text-slate-700 hover:text-crimson-700 dark:text-slate-200"><?= esc(trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'])) ?></a>
            <span class="ml-1 rounded border border-slate-700 bg-amber-300 px-1.5 font-mono text-[11px] font-bold text-slate-900"><?= esc($vehicle['registration']) ?></span>
            · <?php if (!$isMech): ?><a href="<?= site_url('customers/' . $customer['id']) ?>" class="hover:text-crimson-700"><?= esc(customer_name($customer)) ?></a><?php else: ?><?= esc(customer_name($customer)) ?><?php endif; ?>
            <?php if ($job['bay']): ?>· <?= esc($job['bay']) ?><?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/timer') ?>"><?= csrf_field() ?>
            <?php if ($running): ?>
                <button class="btn bg-red-600 text-white hover:bg-red-700" x-data="{ s: <?= time() - strtotime($running['started_at']) ?> }" x-init="setInterval(() => s++, 1000)">
                    <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span></span>
                    Clock off <span class="font-mono" x-text="new Date(s * 1000).toISOString().substr(11, 8)"></span>
                </button>
            <?php else: ?>
                <button class="btn bg-emerald-600 text-white hover:bg-emerald-700"><?= icon('play', 'h-4 w-4') ?> Clock on</button>
            <?php endif; ?>
        </form>
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="btn-dark">Move to… <?= icon('arrow-down', 'h-3.5 w-3.5') ?></button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-navy-900">
                <?php foreach ($statuses as $k => $s): if ($isMech && in_array($k, ['invoiced', 'collected', 'cancelled'], true)) continue; ?>
                    <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/status') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $k ?>">
                        <button class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5 <?= $k === $job['status'] ? 'font-bold' : '' ?>"><span class="h-2.5 w-2.5 rounded-full" style="background: <?= $s['hex'] ?>"></span><?= $s['label'] ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="btn-light"><?= icon('printer', 'h-4 w-4') ?> Documents</button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-navy-900">
                <a href="<?= site_url('job-cards/' . $job['id'] . '/pdf') ?>" target="_blank" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5">Job card PDF</a>
                <a href="<?= site_url('job-cards/' . $job['id'] . '/pdf?prices=0') ?>" target="_blank" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5">Workshop copy (no prices)</a>
                <a href="<?= site_url('job-cards/' . $job['id'] . '/health-report') ?>" target="_blank" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5">Vehicle health report</a>
                <?php if (!$isMech): ?><a href="<?= site_url('job-cards/' . $job['id'] . '/health-report?email=1') ?>" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5">Email health report to customer</a><?php endif; ?>
            </div>
        </div>
        <?php if (!$isMech): ?>
            <a href="<?= site_url('job-cards/' . $job['id'] . '/edit') ?>" class="btn-light"><?= icon('edit', 'h-4 w-4') ?> Edit</a>
            <?php if ($invoice): ?>
                <a href="<?= site_url('invoices/' . $invoice['id']) ?>" class="btn-primary"><?= icon('invoice', 'h-4 w-4') ?> <?= esc($invoice['number']) ?></a>
            <?php else: ?>
                <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/invoice') ?>" data-confirm="Create a tax invoice from this job card? Stock will be deducted."><?= csrf_field() ?><button class="btn-primary"><?= icon('invoice', 'h-4 w-4') ?> Create invoice</button></form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Progress stepper -->
<div class="card mb-6 overflow-x-auto p-5">
    <ol class="flex min-w-[640px] items-center">
        <?php foreach ($flow as $i => $k): $done = $curIdx !== false && $i <= $curIdx; ?>
            <li class="flex flex-1 items-center <?= $i === count($flow) - 1 ? 'flex-none' : '' ?>">
                <div class="flex flex-col items-center gap-1.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold <?= $done ? 'bg-gradient-to-br from-crimson-700 to-brand-red text-white shadow-glow' : 'bg-slate-100 text-slate-400 dark:bg-white/10' ?>"><?= $done ? icon('check', 'h-4 w-4') : $i + 1 ?></span>
                    <span class="whitespace-nowrap text-[11px] font-semibold <?= $done ? 'text-slate-800 dark:text-white' : 'text-slate-400' ?>"><?= $statuses[$k]['label'] ?></span>
                </div>
                <?php if ($i < count($flow) - 1): ?><div class="mx-2 mb-5 h-0.5 flex-1 <?= $curIdx !== false && $i < $curIdx ? 'bg-crimson-600' : 'bg-slate-200 dark:bg-white/10' ?>"></div><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2" x-data="{ tab: '<?= esc(service('request')->getGet('tab') ?: 'work') ?>' }">
        <div class="card">
            <div class="tabs px-3">
                <?php foreach (['work' => 'Work & parts', 'inspection' => 'Inspection (' . ($insp['advisory'] + $insp['urgent']) . ' flagged)', 'checklist' => 'Checklist (' . count(array_filter($checklist, static fn ($c) => $c['is_done'])) . '/' . count($checklist) . ')', 'damage' => 'Damage map', 'time' => 'Time (' . round($totalMins / 60, 1) . 'h)', 'photos' => 'Photos & files (' . count($files) . ')', 'signature' => 'Sign-off', 'activity' => 'Activity'] as $k => $l): ?>
                    <button @click="tab = '<?= $k ?>'" class="tab" :class="tab === '<?= $k ?>' && 'active'"><?= $l ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Work -->
            <div x-show="tab === 'work'" class="space-y-5 p-5">
                <?php if (\App\Libraries\AiAssistant::enabled()): ?>
                <div class="flex flex-wrap gap-2 rounded-2xl bg-gradient-to-r from-navy-50 to-crimson-50 p-3 dark:from-white/5 dark:to-white/5" x-data="{ busy: false, async summary() { this.busy = true; try { await wtPost('<?= site_url('assistant/summary/' . $job['id']) ?>', { save: 1 }); toast('Customer summary written to Work performed'); setTimeout(() => location.reload(), 700); } catch (e) { toast(e.message, 'error'); } this.busy = false; } }">
                    <span class="flex items-center gap-2 text-sm font-semibold text-navy-900 dark:text-white"><?= icon('zap', 'h-4 w-4 text-crimson-600') ?> AI assistant</span>
                    <a href="<?= site_url('assistant?job=' . $job['id']) ?>" class="btn-light btn-sm">Diagnose this job</a>
                    <?php if (!$isMech): ?><button type="button" @click="summary()" :disabled="busy" class="btn-light btn-sm"><span x-show="!busy">Write customer summary</span><span x-show="busy" x-cloak>Writing…</span></button><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php foreach (['customer_complaint' => 'Customer complaint', 'diagnosis' => 'Diagnosis', 'work_performed' => 'Work performed', 'recommendations' => 'Recommendations'] as $f => $l): if (!$job[$f]) continue; ?>
                    <div><div class="label"><?= $l ?></div><p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= esc($job[$f]) ?></p></div>
                <?php endforeach; ?>
                <?php if ($job['internal_notes'] && !$isMech): ?><div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"><b>Internal:</b> <?= esc($job['internal_notes']) ?></div><?php endif; ?>
                <div class="table-wrap -mx-5"><table class="table">
                    <thead><tr><th>Type</th><th>Description</th><th class="text-right">Qty / hrs</th><?php if ($showPrices): ?><th class="text-right">Unit</th><th class="text-right">Total</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr><td><span class="<?= $it['item_type'] === 'labour' ? 'badge-navy' : 'badge-gray' ?>"><?= esc(\App\Libraries\LineItems::TYPES[$it['item_type']] ?? $it['item_type']) ?></span></td>
                            <td class="whitespace-normal"><?= esc($it['description']) ?><?php if ($it['mechanic_name']): ?><div class="text-xs text-slate-500">by <?= esc($it['mechanic_name']) ?></div><?php endif; ?></td>
                            <td class="text-right"><?= (float) $it['quantity'] ?></td>
                            <?php if ($showPrices): ?><td class="text-right"><?= money($it['unit_price']) ?></td><td class="text-right font-semibold"><?= money($it['line_total']) ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr><td colspan="5" class="py-8 text-center text-slate-500">No labour or parts yet. <?php if (!$isMech): ?><a href="<?= site_url('job-cards/' . $job['id'] . '/edit') ?>" class="font-semibold text-crimson-700">Add items</a><?php endif; ?></td></tr><?php endif; ?>
                    </tbody>
                    <?php if ($showPrices && $items): ?>
                    <tfoot class="text-sm"><tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">Subtotal</td><td class="px-4 py-2 text-right"><?= money($job['subtotal'] - $job['discount_total']) ?></td></tr>
                        <tr><td colspan="4" class="px-4 py-1 text-right text-slate-500">VAT</td><td class="px-4 py-1 text-right"><?= money($job['vat_total']) ?></td></tr>
                        <tr><td colspan="4" class="px-4 py-2 text-right font-bold">Total</td><td class="px-4 py-2 text-right text-base font-bold"><?= money($job['total']) ?></td></tr></tfoot>
                    <?php endif; ?>
                </table></div>
            </div>

            <!-- Digital vehicle inspection -->
            <div x-show="tab === 'inspection'" x-cloak class="p-5" x-data="{ async set(id, data) { try { await wtPost('<?= site_url('job-cards/' . $job['id'] . '/inspection') ?>', { item_id: id, ...data }); } catch (e) { toast(e.message, 'error'); } } }">
                <div class="mb-5 grid grid-cols-4 gap-2 text-center text-xs font-semibold">
                    <div class="rounded-xl bg-emerald-50 p-3 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><div class="text-xl font-bold"><?= $insp['ok'] ?></div>Good</div>
                    <div class="rounded-xl bg-amber-50 p-3 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><div class="text-xl font-bold"><?= $insp['advisory'] ?></div>Advisory</div>
                    <div class="rounded-xl bg-red-50 p-3 text-red-700 dark:bg-red-500/10 dark:text-red-300"><div class="text-xl font-bold"><?= $insp['urgent'] ?></div>Urgent</div>
                    <div class="rounded-xl bg-slate-50 p-3 text-slate-600 dark:bg-white/5 dark:text-slate-300"><div class="text-xl font-bold"><?= $insp['pending'] ?></div>Not checked</div>
                </div>
                <?php foreach ($sections as $section => $list): ?>
                    <h3 class="mb-2 mt-5 text-xs font-bold uppercase tracking-wider text-slate-500"><?= esc($section) ?></h3>
                    <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 dark:divide-white/10 dark:border-white/10">
                        <?php foreach ($list as $it): ?>
                            <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center" x-data="{ s: '<?= esc($it['status'] ?? '') ?>' }">
                                <div class="flex-1 text-sm font-medium"><?= esc($it['item']) ?></div>
                                <div class="flex gap-1">
                                    <?php foreach (['ok' => ['Good', 'bg-emerald-500'], 'advisory' => ['Soon', 'bg-amber-400'], 'urgent' => ['Urgent', 'bg-red-500'], 'na' => ['N/A', 'bg-slate-400']] as $k => [$l, $c]): ?>
                                        <button type="button" @click="s = (s === '<?= $k ?>' ? '' : '<?= $k ?>'); set(<?= $it['id'] ?>, { status: s })"
                                                :class="s === '<?= $k ?>' ? '<?= $c ?> text-white border-transparent' : 'border-slate-200 text-slate-500 dark:border-white/10'"
                                                class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition"><?= $l ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" value="<?= esc($it['note']) ?>" placeholder="Note…" @change="set(<?= $it['id'] ?>, { note: $event.target.value })" class="input py-1 text-xs sm:w-48">
                                <?php if ($showPrices): ?><input type="number" step="0.01" min="0" x-show="s === 'advisory' || s === 'urgent'" value="<?= (float) $it['estimate'] ?: '' ?>" placeholder="Quote R" title="Price excl. VAT shown to the customer for online approval" @change="set(<?= $it['id'] ?>, { estimate: $event.target.value })" class="input w-24 py-1 text-xs"><?php endif; ?>
                                <?php if ($it['customer_decision']): ?><span class="<?= $it['customer_decision'] === 'approved' ? 'badge-green' : 'badge-gray' ?>"><?= ucfirst($it['customer_decision']) ?></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/inspection') ?>" class="mt-5 flex gap-2"><?= csrf_field() ?>
                    <input name="item" placeholder="Add a custom inspection point…" class="input flex-1"><button class="btn-light">Add</button>
                </form>
                <p class="mt-3 text-xs text-slate-500">Tip: attach photos of worn parts in <b>Photos &amp; files</b>. Flagged items appear on the job card PDF and the vehicle's history.</p>
            </div>

            <!-- Checklist -->
            <div x-show="tab === 'checklist'" x-cloak class="p-5" x-data="{ async tick(id, done) { try { await wtPost('<?= site_url('job-cards/' . $job['id'] . '/checklist') ?>', { item_id: id, done: done ? 1 : 0 }); } catch (e) { toast(e.message, 'error'); } } }">
                <?php $doneCount = count(array_filter($checklist, static fn ($c) => $c['is_done'])); $pct = $checklist ? round($doneCount / count($checklist) * 100) : 0; ?>
                <div class="mb-4 flex items-center gap-3"><div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-crimson-700 to-brand-red" style="width: <?= $pct ?>%"></div></div><span class="text-sm font-semibold"><?= $pct ?>%</span></div>
                <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200 dark:divide-white/10 dark:border-white/10">
                    <?php foreach ($checklist as $c): ?>
                        <li class="flex items-center gap-3 px-4 py-3" x-data="{ done: <?= $c['is_done'] ? 'true' : 'false' ?> }">
                            <input type="checkbox" x-model="done" @change="tick(<?= $c['id'] ?>, done)" class="h-5 w-5 rounded text-crimson-600 focus:ring-crimson-500">
                            <span class="flex-1 text-sm" :class="done && 'text-slate-400 line-through'"><?= esc($c['item']) ?></span>
                            <?php if ($c['is_done']): ?><span class="text-xs text-slate-400"><?= esc($c['done_by_name']) ?> · <?= date('H:i', strtotime($c['done_at'])) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$checklist): ?><li class="px-4 py-6 text-center text-sm text-slate-500">No checklist for this job type. <?php if (can('settings')): ?><a href="<?= site_url('checklists') ?>" class="font-semibold text-crimson-700">Create checklist templates</a><?php endif; ?></li><?php endif; ?>
                </ul>
                <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/checklist') ?>" class="mt-4 flex gap-2"><?= csrf_field() ?><input name="item" placeholder="Add a step…" class="input flex-1"><button class="btn-light">Add</button></form>
            </div>

            <!-- Damage map -->
            <div x-show="tab === 'damage'" x-cloak class="p-5">
                <?= view('partials/damage_diagram', ['marks' => json_decode((string) $job['damage_marks'], true) ?: [], 'editable' => true, 'saveUrl' => site_url('job-cards/' . $job['id'] . '/damage')]) ?>
            </div>

            <!-- Time -->
            <div x-show="tab === 'time'" x-cloak>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Technician</th><th>Started</th><th>Ended</th><th class="text-right">Duration</th></tr></thead>
                    <tbody>
                    <?php foreach ($timeLogs as $t): ?>
                        <tr><td class="font-medium"><?= esc($t['first_name'] . ' ' . $t['last_name']) ?></td><td><?= fdatetime($t['started_at']) ?></td><td><?= $t['ended_at'] ? fdatetime($t['ended_at']) : '<span class="badge-green">Running</span>' ?></td><td class="text-right font-semibold"><?= $t['ended_at'] ? intdiv((int) $t['minutes'], 60) . 'h ' . ((int) $t['minutes'] % 60) . 'm' : '—' ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$timeLogs): ?><tr><td colspan="4" class="py-8 text-center text-slate-500">No time logged yet. Technicians use <b>Clock on</b> to track time.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>

            <div x-show="tab === 'photos'" x-cloak class="p-5"><?= view('partials/attachments', ['files' => $files, 'entity' => 'job_card', 'entityId' => $job['id'], 'defaultCategory' => 'photo']) ?></div>

            <!-- Signature -->
            <div x-show="tab === 'signature'" x-cloak class="p-5">
                <?php if ($job['customer_signature']): ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10"><img src="<?= esc($job['customer_signature'], 'attr') ?>" alt="Customer signature" class="mx-auto h-32"></div>
                    <p class="mt-2 text-center text-xs text-slate-500">Signed by customer on <?= fdatetime($job['signed_at']) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/signature') ?>" x-data="{ pad: null, init() { const c = this.$refs.c; const r = () => { const ratio = Math.max(window.devicePixelRatio || 1, 1); c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio; c.getContext('2d').scale(ratio, ratio); this.pad && this.pad.clear(); }; this.pad = new SignaturePad(c, { penColor: '#0b0d2e' }); r(); } }" @submit="if (pad.isEmpty()) { $event.preventDefault(); toast('Please sign first', 'error'); } else { $refs.sig.value = pad.toDataURL('image/png'); }" class="mt-4">
                    <?= csrf_field() ?><input type="hidden" name="signature" x-ref="sig">
                    <p class="mb-2 text-sm text-slate-600 dark:text-slate-300"><?= $job['customer_signature'] ? 'Capture a new signature' : 'Customer signature: authorising the work &amp; accepting the terms below' ?></p>
                    <canvas x-ref="c" class="h-48 w-full touch-none rounded-2xl border-2 border-dashed border-slate-300 bg-white dark:border-white/20"></canvas>
                    <div class="mt-3 flex gap-2"><button type="button" @click="pad.clear()" class="btn-light">Clear</button><button class="btn-primary">Save signature</button></div>
                    <p class="mt-4 text-xs leading-relaxed text-slate-500"><?= esc(setting('job_card_terms')) ?></p>
                </form>
            </div>

            <div x-show="tab === 'activity'" x-cloak class="p-6"><?= view('partials/activity', ['activity' => $activity]) ?></div>
        </div>
    </div>

    <!-- Side panel -->
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Summary</div></div>
            <dl class="card-body space-y-3 text-sm">
                <?php foreach ([
                    'Job type' => $job['job_type'], 'Odometer in' => $job['odometer_in'] ? number_format($job['odometer_in']) . ' km' : null, 'Fuel' => $job['fuel_level'],
                    'Booked for' => $job['booked_for'] ? fdatetime($job['booked_for']) : null, 'Checked in' => $job['checked_in_at'] ? fdatetime($job['checked_in_at']) : null,
                    'Promised' => $job['promised_at'] ? fdatetime($job['promised_at']) : null, 'Completed' => $job['completed_at'] ? fdatetime($job['completed_at']) : null,
                    'Advisor' => $advisor ? $advisor['first_name'] . ' ' . $advisor['last_name'] : null,
                ] as $k => $v): if (!$v) continue; ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500"><?= $k ?></dt><dd class="text-right font-medium"><?= esc($v) ?></dd></div>
                <?php endforeach; ?>
                <?php if ($quote): ?><div class="flex justify-between gap-4"><dt class="text-slate-500">From quote</dt><dd><a href="<?= site_url('quotes/' . $quote['id']) ?>" class="font-semibold text-crimson-700"><?= esc($quote['number']) ?></a></dd></div><?php endif; ?>
            </dl>
        </div>
        <!-- Share with customer -->
        <?php $trackUrl = site_url('track/' . $job['public_token']); $st = job_statuses()[$job['status']]['label'] ?? $job['status']; ?>
        <div class="card"><div class="card-header"><div class="card-title">Keep the customer updated</div></div>
            <div class="card-body space-y-3">
                <div x-data="{ copied: false }" class="flex gap-2">
                    <input readonly value="<?= $trackUrl ?>" class="input flex-1 bg-slate-50 text-xs dark:bg-white/5">
                    <button type="button" class="btn-light btn-sm" @click="navigator.clipboard.writeText('<?= $trackUrl ?>'); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy'"></span></button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?= wa_button($customer['phone'], 'Hi ' . customer_name($customer) . ', your ' . trim($vehicle['make'] . ' ' . $vehicle['model']) . ' (' . $vehicle['registration'] . ') at ' . setting('company_name') . ' is now: ' . $st . '. Track live: ' . $trackUrl, 'Status update') ?>
                    <a href="<?= $trackUrl ?>" target="_blank" class="btn-light btn-sm"><?= icon('eye', 'h-3.5 w-3.5') ?> Customer view</a>
                </div>
                <p class="text-xs text-slate-500">The live tracker shows progress, inspection findings to approve, photos and the invoice pay link.</p>
            </div>
        </div>

        <?php if ($showPrices): ?>
        <!-- Job profitability -->
        <div class="card"><div class="card-header"><div class="card-title">Job profitability</div><span class="<?= $costing['margin'] >= 40 ? 'badge-green' : ($costing['margin'] >= 20 ? 'badge-amber' : 'badge-red') ?>"><?= $costing['margin'] ?>% margin</span></div>
            <dl class="card-body space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Revenue (excl. VAT)</dt><dd class="font-semibold"><?= money($costing['revenue']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Parts cost</dt><dd>-<?= money($costing['parts_cost']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Labour cost (<?= $costing['hours_clocked'] ?>h clocked)</dt><dd>-<?= money($costing['labour_cost']) ?></dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-bold dark:border-white/10"><dt>Gross profit</dt><dd class="<?= $costing['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= money($costing['profit']) ?></dd></div>
                <?php if ($costing['efficiency'] !== null): ?><div class="flex justify-between text-xs"><dt class="text-slate-500">Labour efficiency (sold ÷ clocked)</dt><dd class="font-semibold"><?= $costing['efficiency'] ?>%</dd></div><?php endif; ?>
            </dl>
        </div>

        <!-- Deposits -->
        <div class="card"><div class="card-header"><div class="card-title">Deposits</div><span class="text-sm font-semibold"><?= money(array_sum(array_column($deposits, 'amount'))) ?></span></div>
            <div class="card-body space-y-2 text-sm">
                <?php foreach ($deposits as $dp): ?><div class="flex justify-between"><span><?= esc($dp['number']) ?> · <?= strtoupper(esc($dp['method'])) ?></span><span class="<?= $dp['status'] === 'applied' ? 'badge-green' : 'badge-amber' ?>"><?= money($dp['amount']) ?> <?= $dp['status'] ?></span></div><?php endforeach; ?>
                <?php if (!$invoice): ?>
                <form method="post" action="<?= site_url('deposits/save') ?>" class="flex gap-2 pt-1" x-data><?= csrf_field() ?><input type="hidden" name="job_card_id" value="<?= $job['id'] ?>">
                    <input name="amount" type="number" step="0.01" min="1" placeholder="Amount" required class="input py-1 text-xs">
                    <select name="method" class="input w-24 py-1 text-xs"><option value="eft">EFT</option><option value="card">Card</option><option value="cash">Cash</option></select>
                    <button class="btn-dark btn-sm">Take</button>
                </form>
                <p class="text-xs text-slate-500">Applied automatically to the invoice.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loan car -->
        <div class="card"><div class="card-header"><div class="card-title">Courtesy car</div></div>
            <div class="card-body text-sm">
                <?php if ($loan): ?>
                    <div class="flex items-center justify-between"><span><span class="rounded border border-slate-800 bg-amber-300 px-1.5 font-mono text-xs font-bold text-slate-900"><?= esc($loan['registration']) ?></span> <?= esc($loan['description']) ?></span><span class="badge-amber">Out</span></div>
                    <p class="mt-1 text-xs text-slate-500">Since <?= fdatetime($loan['out_at']) ?><?= $loan['due_at'] ? ' · due ' . fdatetime($loan['due_at']) : '' ?></p>
                    <form method="post" action="<?= site_url('loans/' . $loan['id'] . '/return') ?>" class="mt-2 flex gap-2"><?= csrf_field() ?><input name="odometer_in" type="number" placeholder="km in" class="input py-1 text-xs"><button class="btn-dark btn-sm">Returned</button></form>
                <?php elseif ($loanCars): ?>
                    <form method="post" action="<?= site_url('loans/out') ?>" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="job_card_id" value="<?= $job['id'] ?>">
                        <select name="loan_vehicle_id" class="input py-1.5 text-sm"><?php foreach ($loanCars as $lc): ?><option value="<?= $lc['id'] ?>"><?= esc($lc['registration'] . ' · ' . $lc['description']) ?></option><?php endforeach; ?></select>
                        <div class="grid grid-cols-2 gap-2"><input type="datetime-local" name="due_at" value="<?= $job['promised_at'] ? date('Y-m-d\TH:i', strtotime($job['promised_at'])) : '' ?>" class="input py-1 text-xs"><input name="licence_number" placeholder="Licence no." class="input py-1 text-xs"></div>
                        <button class="btn-light btn-sm w-full">Issue courtesy car</button>
                    </form>
                <?php else: ?>
                    <p class="text-slate-500">No courtesy car available. <a href="<?= site_url('loans') ?>" class="font-semibold text-crimson-700">Manage fleet</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card"><div class="card-header"><div class="card-title">Technicians</div></div>
            <ul class="card-body space-y-3">
                <?php foreach ($mechanics as $m): ?>
                    <li class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white dark:bg-white/10"><?= esc(initials($m['first_name'] . ' ' . $m['last_name'])) ?></span><span class="text-sm font-semibold"><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></span></li>
                <?php endforeach; ?>
                <?php if (!$mechanics): ?><li class="text-sm text-slate-500">Nobody assigned yet.</li><?php endif; ?>
            </ul>
        </div>
        <?php if ($showPrices): ?>
        <div class="card overflow-hidden">
            <div class="bg-gradient-to-br from-navy-900 to-navy-700 p-5 text-white">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">Job value (incl. VAT)</div>
                <div class="mt-1 text-3xl font-extrabold"><?= money($job['total']) ?></div>
                <div class="mt-1 text-xs text-slate-300"><?= count($items) ?> lines · VAT <?= money($job['vat_total']) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!$isMech): ?>
        <div class="flex justify-end"><form method="post" action="<?= site_url('job-cards/' . $job['id'] . '/delete') ?>" data-confirm="Cancel and archive this job card?"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600"><?= icon('trash', 'h-3.5 w-3.5') ?> Cancel job card</button></form></div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/signature_pad.min.js') ?>"></script>
<?= $this->endSection() ?>
