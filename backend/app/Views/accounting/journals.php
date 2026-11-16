<?php $src = service('request')->getGet('source'); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Journals<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Journal entries', 'subtitle' => 'The complete audit trail of every posting to the ledger.', 'crumbs' => ['accounting' => 'Accounting'], 'actions' => '<a href="' . site_url('accounting/journals/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Manual journal</a>']) ?>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Journal no., description or reference…" class="input flex-1">
        <select name="source" class="input sm:w-44"><option value="">All sources</option><?php foreach (['manual', 'invoice', 'payment', 'credit_note', 'expense', 'bill', 'supplier_payment', 'bank', 'vat_return', 'vat_payment', 'reversal', 'opening'] as $s): ?><option value="<?= $s ?>" <?= $src === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= esc($from) ?>" class="input sm:w-40"><input type="date" name="to" value="<?= esc($to) ?>" class="input sm:w-40">
        <button class="btn-dark">Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Journal</th><th>Date</th><th>Description</th><th>Source</th><th>By</th><th class="text-right">Amount</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer <?= $r['reversed_by'] ? 'opacity-50' : '' ?>" onclick="location.href='<?= site_url('accounting/journals/' . $r['id']) ?>'">
                <td class="font-mono text-xs font-semibold"><?= esc($r['number']) ?></td><td><?= fdate($r['entry_date']) ?></td>
                <td class="max-w-md truncate"><?= esc($r['description']) ?><?= $r['reversed_by'] ? ' <span class="badge-red">reversed</span>' : '' ?></td>
                <td><span class="badge-gray"><?= esc(str_replace('_', ' ', $r['source_type'])) ?></span></td>
                <td class="text-slate-500"><?= esc($r['first_name'] ?: 'System') ?></td>
                <td class="text-right font-semibold"><?= money($r['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="py-10 text-center text-slate-500">No journals in this period.</td></tr><?php endif; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
</div>
<?= $this->endSection() ?>
