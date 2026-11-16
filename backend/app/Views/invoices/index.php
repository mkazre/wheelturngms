<?php $cur = service('request')->getGet('status'); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Invoices<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Invoices', 'subtitle' => 'SARS-compliant tax invoices with PayFast &amp; Ozow pay-online links.',
    'actions' => view('partials/export_buttons', ['base' => 'invoices']) . '<a href="' . site_url('invoices/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New invoice</a>']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Invoiced this month', 'value' => money_short($stats['month']), 'icon' => 'invoice', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Collected this month', 'value' => money_short($stats['collected']), 'icon' => 'wallet', 'tone' => 'green']) ?>
    <?= view('partials/kpi', ['label' => 'Outstanding', 'value' => money_short($stats['outstanding']), 'icon' => 'clock', 'tone' => 'amber']) ?>
    <?= view('partials/kpi', ['label' => 'Overdue', 'value' => money_short($stats['overdue']), 'icon' => 'alert', 'tone' => 'crimson']) ?>
</div>
<div class="mb-4 flex gap-2 overflow-x-auto pb-1">
    <?php foreach (['' => 'All', 'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'partial' => 'Part paid', 'paid' => 'Paid', 'draft' => 'Drafts', 'void' => 'Void'] as $k => $l): ?>
        <a href="?status=<?= $k ?>" class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold <?= (string) $cur === (string) $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input type="hidden" name="status" value="<?= esc($cur) ?>">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search invoice no., customer, registration or order no.…" class="input flex-1">
        <input type="date" name="from" value="<?= esc(service('request')->getGet('from')) ?>" class="input sm:w-40"><input type="date" name="to" value="<?= esc(service('request')->getGet('to')) ?>" class="input sm:w-40">
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Invoice</th><th>Customer</th><th>Vehicle</th><th>Status</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
        <tbody><?php foreach ($rows as $r): $ds = invoice_display_status($r); ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('invoices/' . $r['id']) ?>'">
                <td><div class="font-semibold text-slate-900 dark:text-white"><?= esc($r['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($r['issue_date']) ?></div></td>
                <td><?= esc(customer_name($r)) ?></td>
                <td><?= $r['registration'] ? '<span class="font-mono text-xs">' . esc($r['registration']) . '</span>' : '—' ?></td>
                <td><?= status_badge($ds, 'invoice') ?></td>
                <td class="<?= $ds === 'overdue' ? 'font-semibold text-red-600' : 'text-slate-500' ?>"><?= fdate($r['due_date']) ?></td>
                <td class="text-right"><?= money($r['total']) ?></td>
                <td class="text-right font-semibold"><?= (float) $r['balance_due'] > 0 ? money($r['balance_due']) : '<span class="text-emerald-600">Paid</span>' ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'invoice', 'title' => 'No invoices found', 'text' => 'Invoices are usually created from a completed job card.']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
