<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Follow-ups<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Deferred work &amp; follow-ups', 'subtitle' => 'Inspection findings the customer has not had done yet. Follow up to win the work and keep their vehicle safe.']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
    <?= view('partials/kpi', ['label' => 'Open recommendations', 'value' => count($rows), 'icon' => 'clipboard', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Urgent safety items', 'value' => count(array_filter($rows, static fn ($r) => $r['status'] === 'urgent')), 'icon' => 'alert', 'tone' => 'crimson']) ?>
    <?= view('partials/kpi', ['label' => 'Quoted value waiting', 'value' => money_short($value), 'icon' => 'wallet', 'tone' => 'green', 'hint' => 'where an estimate was given']) ?>
</div>
<div class="card">
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Finding</th><th>Vehicle</th><th>Customer</th><th>From job</th><th class="text-right">Estimate</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr>
                <td class="whitespace-normal"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full <?= $r['status'] === 'urgent' ? 'bg-red-500' : 'bg-amber-400' ?>"></span><span class="font-semibold"><?= esc($r['item']) ?></span><?= $r['customer_decision'] === 'declined' ? '<span class="badge-gray">declined</span>' : '' ?></div><div class="text-xs text-slate-500"><?= esc($r['note']) ?></div></td>
                <td><a href="<?= site_url('vehicles/' . $r['vehicle_id']) ?>" class="font-mono text-xs font-semibold"><?= esc($r['registration']) ?></a><div class="text-xs text-slate-500"><?= esc($r['make'] . ' ' . $r['model']) ?></div></td>
                <td><?= esc(customer_name($r)) ?><div class="text-xs text-slate-500"><?= esc($r['phone']) ?></div></td>
                <td><a href="<?= site_url('job-cards/' . $r['job_id']) ?>" class="text-crimson-700"><?= esc($r['number']) ?></a><div class="text-xs text-slate-500"><?= time_ago($r['job_date']) ?></div></td>
                <td class="text-right font-semibold"><?= (float) $r['estimate'] ? money($r['estimate']) : '—' ?></td>
                <td><div class="flex justify-end gap-1">
                    <?= wa_button($r['phone'], 'Hi ' . customer_name($r) . ', at your last visit our technician recommended: ' . $r['item'] . ($r['note'] ? ' (' . $r['note'] . ')' : '') . ' on your ' . $r['make'] . ' ' . $r['registration'] . '. Would you like us to book it in? ' . site_url('book'), 'Follow up') ?>
                    <a href="<?= site_url('quotes/new?customer_id=' . $r['customer_id'] . '&vehicle_id=' . $r['vehicle_id']) ?>" class="btn-light btn-sm">Quote</a>
                    <form method="post" action="<?= site_url('follow-ups/' . $r['id'] . '/done') ?>"><?= csrf_field() ?><button class="btn-ghost btn-sm" title="Mark followed up"><?= icon('check', 'h-4 w-4') ?></button></form>
                </div></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php else: ?><?= view('partials/empty', ['icon' => 'check', 'title' => 'No outstanding recommendations', 'text' => 'Flag inspection items as "Soon" or "Urgent" on job cards and they will appear here after the job.']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
