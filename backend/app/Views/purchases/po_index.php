<?php $cur = service('request')->getGet('status'); $badge = ['draft' => 'badge-gray', 'sent' => 'badge-blue', 'billed' => 'badge-green', 'cancelled' => 'badge-red']; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Purchase Orders<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Purchase orders', 'subtitle' => 'Order parts from suppliers. Receiving a PO updates stock and creates the supplier bill. ' . money($open) . ' on open orders.',
    'actions' => view('partials/export_buttons', ['base' => 'purchase-orders']) . '<a href="' . site_url('bills') . '" class="btn-light">' . icon('receipt', 'h-4 w-4') . ' Supplier bills</a><a href="' . site_url('purchase-orders/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New PO</a>']) ?>
<div class="mb-4 flex gap-2 overflow-x-auto">
    <?php foreach (['' => 'All', 'draft' => 'Draft', 'sent' => 'Ordered', 'billed' => 'Received', 'cancelled' => 'Cancelled'] as $k => $l): ?><a href="?status=<?= $k ?>" class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold <?= (string) $cur === $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' ?>"><?= $l ?></a><?php endforeach; ?>
</div>
<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10"><input type="hidden" name="status" value="<?= esc($cur) ?>"><input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="PO number, supplier or job card…" class="input flex-1"><button class="btn-dark">Search</button></form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>PO</th><th>Supplier</th><th>Job card</th><th>Expected</th><th>Status</th><th class="text-right">Total</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('purchase-orders/' . $r['id']) ?>'">
                <td><div class="font-semibold"><?= esc($r['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($r['order_date']) ?></div></td>
                <td><?= esc($r['supplier_name']) ?></td><td><?= esc($r['job_number'] ?: '—') ?></td><td class="text-slate-500"><?= fdate($r['expected_date']) ?></td>
                <td><span class="<?= $badge[$r['status']] ?? 'badge-gray' ?>"><?= ['draft' => 'Draft', 'sent' => 'Ordered', 'billed' => 'Received', 'cancelled' => 'Cancelled'][$r['status']] ?? $r['status'] ?></span></td>
                <td class="text-right font-semibold"><?= money($r['total']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'truck', 'title' => 'No purchase orders', 'action' => '<a href="' . site_url('purchase-orders/new') . '" class="btn-primary">Create PO</a>']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
