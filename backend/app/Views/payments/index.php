<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Payments<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Payments received', 'subtitle' => fdate($from) . ' – ' . fdate($to) . ' · ' . money($total) . ' collected', 'actions' => view('partials/export_buttons', ['base' => 'payments'])]) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
    <?php foreach (['eft' => ['EFT', 'navy'], 'card' => ['Card', 'violet'], 'cash' => ['Cash', 'green'], 'payfast' => ['PayFast', 'crimson'], 'ozow' => ['Ozow', 'sky'], 'other' => ['Other', 'amber']] as $k => [$l, $tone]): ?>
        <?= view('partials/kpi', ['label' => $l, 'value' => money_short($byMethod[$k] ?? 0), 'icon' => 'wallet', 'tone' => $tone]) ?>
    <?php endforeach; ?>
</div>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Receipt, reference, invoice or customer…" class="input flex-1">
        <select name="method" class="input sm:w-40"><option value="">All methods</option><?php foreach (\App\Controllers\Invoices::METHODS as $k => $l): ?><option value="<?= $k ?>" <?= service('request')->getGet('method') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= esc($from) ?>" class="input sm:w-40"><input type="date" name="to" value="<?= esc($to) ?>" class="input sm:w-40">
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="<?= $r['status'] !== 'completed' ? 'opacity-50' : '' ?>">
                <td class="font-semibold"><?= esc($r['number']) ?> <?= $r['status'] !== 'completed' ? status_badge($r['status'], 'payment') : '' ?></td>
                <td><?= fdatetime($r['paid_at']) ?></td>
                <td><?= esc(customer_name($r)) ?></td>
                <td><?php if ($r['invoice_id']): ?><a href="<?= site_url('invoices/' . $r['invoice_id']) ?>" class="font-medium text-crimson-700 hover:underline dark:text-crimson-400"><?= esc($r['invoice_number']) ?></a><?php endif; ?></td>
                <td><span class="badge-gray"><?= strtoupper(esc($r['method'])) ?></span><?php if (!empty($slips[$r['id']])): ?> <span class="badge-blue" title="Slip attached"><?= icon('paperclip', 'h-3 w-3') ?></span><?php endif; ?></td>
                <td class="text-slate-500"><?= esc($r['reference'] ?: $r['gateway_txn_id']) ?></td>
                <td class="text-right font-semibold"><?= money($r['amount']) ?></td>
                <td class="text-right"><div class="flex justify-end gap-1">
                    <a href="<?= site_url('payments/' . $r['id'] . '/receipt') ?>" target="_blank" class="btn-ghost btn-sm">Receipt</a>
                    <?php if ($r['status'] === 'completed' && in_array(session('role'), ['admin', 'manager', 'accountant'], true)): ?>
                        <form method="post" action="<?= site_url('payments/' . $r['id'] . '/delete') ?>" data-confirm="Reverse this payment? The invoice balance and ledger will be updated."><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Reverse</button></form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'card', 'title' => 'No payments in this period']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
