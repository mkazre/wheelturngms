<?php $statuses = job_statuses(); ?>
<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>My account<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="mb-6 flex items-center justify-between">
    <div><h1 class="text-2xl font-bold text-slate-900">Hi <?= esc($c['first_name'] ?: customer_name($c)) ?></h1><p class="text-sm text-slate-500">Track your vehicles, approve quotes and pay invoices.</p></div>
    <a href="<?= site_url('logout') ?>" class="btn-light btn-sm"><?= icon('logout', 'h-4 w-4') ?> Sign out</a>
</div>
<?php $active = array_filter($jobs, static fn ($j) => !in_array($j['status'], ['collected', 'cancelled', 'invoiced'], true)); ?>
<?php if ($active): ?>
    <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-500">In the workshop</h2>
    <div class="mb-8 grid gap-4 sm:grid-cols-2">
        <?php foreach ($active as $j): ?>
            <div class="card p-5"><div class="flex items-start justify-between"><div><div class="font-semibold"><?= esc($j['make'] . ' ' . $j['model']) ?></div><div class="font-mono text-xs text-slate-500"><?= esc($j['registration']) ?> · <?= esc($j['number']) ?></div></div><?= status_badge($j['status']) ?></div>
                <?php if ($j['promised_at']): ?><p class="mt-3 text-sm text-slate-600">Expected ready: <b><?= fdatetime($j['promised_at']) ?></b></p><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card"><div class="card-header"><div class="card-title">Invoices</div></div>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($invoices as $i): $ds = invoice_display_status($i); ?>
                <li class="flex items-center justify-between gap-3 px-5 py-3"><div><div class="font-semibold"><?= esc($i['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($i['issue_date']) ?> · <?= money($i['total']) ?></div></div>
                    <div class="flex items-center gap-2"><?= status_badge($ds, 'invoice') ?><a href="<?= site_url('pay/' . $i['public_token']) ?>" class="<?= in_array($i['status'], ['unpaid', 'partial'], true) ? 'btn-primary' : 'btn-light' ?> btn-sm"><?= in_array($i['status'], ['unpaid', 'partial'], true) ? 'Pay ' . money($i['balance_due']) : 'View' ?></a></div></li>
            <?php endforeach; ?>
            <?php if (!$invoices): ?><li class="px-5 py-6 text-center text-sm text-slate-500">No invoices yet.</li><?php endif; ?>
        </ul>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Quotes</div></div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($quotes as $q): ?><li class="flex items-center justify-between px-5 py-3"><div><div class="font-semibold"><?= esc($q['number']) ?></div><div class="text-xs text-slate-500"><?= money($q['total']) ?></div></div><a href="<?= site_url('q/' . $q['public_token']) ?>" class="btn-light btn-sm"><?= $q['status'] === 'sent' ? 'Review &amp; accept' : 'View' ?></a></li><?php endforeach; ?>
                <?php if (!$quotes): ?><li class="px-5 py-6 text-center text-sm text-slate-500">No open quotes.</li><?php endif; ?>
            </ul>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">My vehicles</div></div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($vehicles as $v): ?><li class="flex items-center justify-between px-5 py-3"><div><div class="font-semibold"><?= esc(trim($v['year'] . ' ' . $v['make'] . ' ' . $v['model'])) ?></div><div class="text-xs text-slate-500"><?= $v['next_service_date'] ? 'Next service ' . fdate($v['next_service_date']) : '' ?></div></div><span class="rounded border border-slate-800 bg-amber-300 px-2 font-mono text-xs font-bold"><?= esc($v['registration']) ?></span></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
