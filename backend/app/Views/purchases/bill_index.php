<?php $cur = service('request')->getGet('status'); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Supplier Bills<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Supplier bills', 'subtitle' => 'Accounts payable: what Wheelsturn owes its suppliers.',
    'actions' => view('partials/export_buttons', ['base' => 'bills']) . '<a href="' . site_url('purchase-orders') . '" class="btn-light">' . icon('truck', 'h-4 w-4') . ' Purchase orders</a><a href="' . site_url('bills/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Capture bill</a>']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Total owing', 'value' => money_short($stats['owing']), 'icon' => 'wallet', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Overdue', 'value' => money_short($stats['overdue']), 'icon' => 'alert', 'tone' => 'crimson']) ?>
    <?= view('partials/kpi', ['label' => 'Due in 7 days', 'value' => money_short($stats['week']), 'icon' => 'calendar', 'tone' => 'amber']) ?>
    <?= view('partials/kpi', ['label' => 'Paid this month', 'value' => money_short($stats['paid_month']), 'icon' => 'check', 'tone' => 'green']) ?>
</div>
<div class="mb-4 flex gap-2 overflow-x-auto">
    <?php foreach (['' => 'All', 'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void'] as $k => $l): ?><a href="?status=<?= $k ?>" class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold <?= (string) $cur === $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' ?>"><?= $l ?></a><?php endforeach; ?>
</div>
<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10"><input type="hidden" name="status" value="<?= esc($cur) ?>"><input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Bill no., supplier invoice no. or supplier…" class="input flex-1"><button class="btn-dark">Search</button></form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Bill</th><th>Supplier</th><th>Supplier inv.</th><th>Due</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
        <tbody><?php foreach ($rows as $r): $late = in_array($r['status'], ['unpaid', 'partial'], true) && $r['due_date'] < date('Y-m-d'); ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('bills/' . $r['id']) ?>'">
                <td><div class="font-semibold"><?= esc($r['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($r['bill_date']) ?></div></td>
                <td><?= esc($r['supplier_name']) ?></td><td class="font-mono text-xs"><?= esc($r['supplier_invoice'] ?: '—') ?></td>
                <td class="<?= $late ? 'font-semibold text-red-600' : 'text-slate-500' ?>"><?= fdate($r['due_date']) ?></td>
                <td><?= status_badge($late ? 'overdue' : $r['status'], 'invoice') ?></td>
                <td class="text-right"><?= money($r['total']) ?></td><td class="text-right font-semibold"><?= money($r['balance_due']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'receipt', 'title' => 'No supplier bills', 'text' => 'Capture supplier invoices here, or receive a purchase order.', 'action' => '<a href="' . site_url('bills/new') . '" class="btn-primary">Capture bill</a>']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
