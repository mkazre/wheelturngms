<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Credit Notes<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Credit notes', 'subtitle' => 'Returns, overcharges and goodwill credits. Raise them from an invoice.', 'crumbs' => ['invoices' => 'Invoices'], 'actions' => view('partials/export_buttons', ['base' => 'credit-notes'])]) ?>
<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10"><input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Credit note, invoice or customer…" class="input flex-1"><button class="btn-dark">Search</button></form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Credit note</th><th>Invoice</th><th>Customer</th><th>Reason</th><th>Status</th><th class="text-right">Total</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('credit-notes/' . $r['id']) ?>'">
                <td><div class="font-semibold"><?= esc($r['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($r['issue_date']) ?></div></td>
                <td><?= esc($r['invoice_number']) ?></td><td><?= esc(customer_name($r)) ?></td>
                <td class="max-w-xs truncate text-slate-500"><?= esc($r['reason']) ?></td>
                <td><span class="<?= $r['status'] === 'issued' ? 'badge-green' : 'badge-red' ?>"><?= ucfirst(esc($r['status'])) ?></span></td>
                <td class="text-right font-semibold">-<?= money($r['total']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'receipt', 'title' => 'No credit notes', 'text' => 'Open an invoice and choose "Credit note" to raise one.']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
