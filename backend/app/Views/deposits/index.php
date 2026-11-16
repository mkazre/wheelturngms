<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Deposits<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Customer deposits', 'subtitle' => 'Money received upfront is held as a liability and applied automatically when the job is invoiced.']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
    <?= view('partials/kpi', ['label' => 'Deposits held', 'value' => money($held), 'icon' => 'wallet', 'tone' => 'navy', 'hint' => 'not yet applied']) ?>
</div>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Receipt</th><th>Customer</th><th>Job card</th><th>Method</th><th>Received</th><th>Status</th><th class="text-right">Amount</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td class="font-semibold"><?= esc($r['number']) ?></td><td><?= esc(customer_name($r)) ?></td>
            <td><?= $r['job_card_id'] ? '<a class="text-crimson-700" href="' . site_url('job-cards/' . $r['job_card_id']) . '">' . esc($r['job_number']) . '</a>' : '—' ?></td>
            <td><span class="badge-gray"><?= strtoupper(esc($r['method'])) ?></span></td><td><?= fdatetime($r['received_at']) ?></td>
            <td><?php if ($r['status'] === 'applied'): ?><a href="<?= site_url('invoices/' . $r['applied_invoice_id']) ?>" class="badge-green">Applied to <?= esc($r['invoice_number']) ?></a><?php else: ?><span class="<?= $r['status'] === 'held' ? 'badge-amber' : 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span><?php endif; ?></td>
            <td class="text-right font-semibold"><?= money($r['amount']) ?></td>
            <td class="text-right"><?php if ($r['status'] === 'held'): ?><form method="post" action="<?= site_url('deposits/' . $r['id'] . '/refund') ?>" data-confirm="Refund this deposit to the customer?"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Refund</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="py-10 text-center text-slate-500">No deposits yet. Take a deposit from any job card.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $this->endSection() ?>
