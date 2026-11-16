<?php
$statuses = job_statuses();
$flow = ['booked' => 'Booked', 'checked_in' => 'Checked in', 'in_progress' => 'Being worked on', 'quality_check' => 'Quality check', 'completed' => 'Ready for collection', 'collected' => 'Collected'];
$order = array_keys($flow);
$cur = $job['status'] === 'invoiced' ? 'completed' : ($job['status'] === 'awaiting_parts' || $job['status'] === 'awaiting_approval' ? 'in_progress' : $job['status']);
$curIdx = array_search($cur, $order, true);
$vatOn = (int) setting('vat_registered', 1) === 1;
?>
<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Track <?= esc($vehicle['registration']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card overflow-hidden">
    <div class="bg-gradient-to-br from-navy-900 via-navy-800 to-crimson-800 p-6 text-white sm:p-8">
        <div class="text-xs font-semibold uppercase tracking-widest text-slate-300">Live vehicle tracker · <?= esc($job['number']) ?></div>
        <h1 class="mt-2 text-2xl font-extrabold sm:text-3xl"><?= esc(trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'])) ?></h1>
        <span class="mt-2 inline-block rounded-md border-2 border-slate-900 bg-amber-300 px-2.5 py-0.5 font-mono text-sm font-extrabold text-slate-900"><?= esc($vehicle['registration']) ?></span>
        <div class="mt-5 text-lg font-bold"><?= esc($statuses[$job['status']]['label'] ?? '') ?><?= $job['status'] === 'awaiting_parts' ? ': waiting for parts' : ($job['status'] === 'awaiting_approval' ? ': waiting for your approval below' : '') ?></div>
        <?php if ($job['promised_at'] && !in_array($job['status'], ['completed', 'invoiced', 'collected'], true)): ?><div class="text-sm text-slate-300">Estimated ready: <b class="text-white"><?= fdatetime($job['promised_at']) ?></b></div><?php endif; ?>
    </div>
    <div class="overflow-x-auto p-6">
        <ol class="flex min-w-[560px] items-start">
            <?php foreach ($flow as $k => $label): $i = array_search($k, $order, true); $done = $curIdx !== false && $i <= $curIdx; ?>
                <li class="flex flex-1 flex-col items-center text-center">
                    <div class="flex w-full items-center"><div class="h-1 flex-1 <?= $i === 0 ? 'opacity-0' : ($done ? 'bg-crimson-600' : 'bg-slate-200') ?>"></div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?= $done ? 'bg-gradient-to-br from-crimson-700 to-brand-red text-white shadow-glow' : 'bg-slate-100 text-slate-400' ?>"><?= $done ? icon('check', 'h-4 w-4') : $i + 1 ?></span>
                        <div class="h-1 flex-1 <?= $i === count($flow) - 1 ? 'opacity-0' : ($curIdx !== false && $i < $curIdx ? 'bg-crimson-600' : 'bg-slate-200') ?>"></div></div>
                    <span class="mt-2 text-xs font-semibold <?= $done ? 'text-slate-800' : 'text-slate-400' ?>"><?= $label ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>

<?php if ($findings): ?>
<div id="findings" class="card mt-6">
    <div class="card-header"><div><div class="card-title">Our technician's findings</div><p class="text-xs text-slate-500">Approve the work you'd like us to do now. Prices exclude VAT<?= $vatOn ? ' (15% added on the invoice)' : '' ?>.</p></div></div>
    <ul class="divide-y divide-slate-100">
        <?php foreach ($findings as $f): ?>
            <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                <span class="mt-1 h-3 w-3 shrink-0 rounded-full <?= $f['status'] === 'urgent' ? 'bg-red-500' : 'bg-amber-400' ?>"></span>
                <div class="flex-1"><div class="font-semibold"><?= esc($f['item']) ?> <span class="<?= $f['status'] === 'urgent' ? 'badge-red' : 'badge-amber' ?>"><?= $f['status'] === 'urgent' ? 'Needs attention now' : 'Attention soon' ?></span></div><div class="text-sm text-slate-500"><?= esc($f['note'] ?: $f['section']) ?></div></div>
                <?php if ((float) $f['estimate'] > 0): ?><div class="text-lg font-bold"><?= money($f['estimate']) ?></div><?php endif; ?>
                <?php if ($f['customer_decision']): ?>
                    <span class="<?= $f['customer_decision'] === 'approved' ? 'badge-green' : 'badge-gray' ?>"><?= ucfirst($f['customer_decision']) ?></span>
                <?php elseif (!in_array($job['status'], ['invoiced', 'collected', 'cancelled'], true)): ?>
                    <form method="post" action="<?= site_url('track/' . $token . '/decide') ?>" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $f['id'] ?>">
                        <button name="decision" value="approve" class="btn-primary btn-sm"><?= icon('check', 'h-3.5 w-3.5') ?> Approve</button><button name="decision" value="decline" class="btn-light btn-sm">Not now</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <?php if ($photos): ?>
    <div class="card"><div class="card-header"><div class="card-title">Photos &amp; videos from the workshop</div></div>
        <div class="grid grid-cols-2 gap-2 p-4">
            <?php foreach ($photos as $p): $src = site_url('track/' . $token . '/file/' . $p['id']); ?>
                <?php if (str_starts_with((string) $p['mime'], 'video/')): ?><video src="<?= $src ?>" controls preload="metadata" class="aspect-video w-full rounded-xl bg-black"></video>
                <?php else: ?><a href="<?= $src ?>" target="_blank"><img src="<?= $src ?>" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-xl object-cover"></a><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="space-y-6">
        <?php if ($invoice): ?>
            <div class="card p-6"><div class="text-xs font-semibold uppercase text-slate-500">Invoice <?= esc($invoice['number']) ?></div><div class="mt-1 text-3xl font-extrabold text-navy-900"><?= money($invoice['status'] === 'paid' ? $invoice['total'] : $invoice['balance_due']) ?></div>
                <?php if ($invoice['status'] === 'paid'): ?><span class="badge-green mt-2">Paid, thank you</span><?php else: ?><a href="<?= site_url('pay/' . $invoice['public_token']) ?>" class="btn-primary mt-4 w-full py-3"><?= icon('card', 'h-5 w-5') ?> View &amp; pay online</a><?php endif; ?></div>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Updates</div></div>
            <ol class="space-y-3 p-5 text-sm">
                <?php foreach (array_reverse($timeline) as $t): ?><li class="flex gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-crimson-600"></span><div><div class="font-medium"><?= esc($t['description']) ?></div><div class="text-xs text-slate-400"><?= fdatetime($t['created_at']) ?></div></div></li><?php endforeach; ?>
            </ol>
        </div>
        <div class="card p-5 text-sm"><div class="font-semibold">Questions?</div><p class="text-slate-500">Call <?= esc(setting('company_phone')) ?> and quote <b><?= esc($job['number']) ?></b>.</p>
            <a href="<?= site_url('track/' . $token . '/job-card') ?>" target="_blank" class="btn-light btn-sm mt-3"><?= icon('download', 'h-3.5 w-3.5') ?> Job card PDF</a></div>
    </div>
</div>
<?= $this->endSection() ?>
