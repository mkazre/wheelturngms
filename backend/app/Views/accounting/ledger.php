<?php $qs = http_build_query(['from' => $from, 'to' => $to]); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Ledger <?= esc($acc['code']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => esc($acc['code'] . ' · ' . $acc['name']), 'subtitle' => 'General ledger account · ' . fdate($from) . ' – ' . fdate($to), 'crumbs' => ['accounting' => 'Accounting', 'accounting/accounts' => 'Chart of accounts'],
    'actions' => '<a href="?' . $qs . '&export=xlsx" class="btn-light"><span class="badge-green">XLSX</span> Excel</a><a href="?' . $qs . '&export=pdf" class="btn-dark"><span class="badge-red">PDF</span> PDF</a>']) ?>
<form class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
    <div class="flex-1"><label class="label">Account</label><select onchange="location.href='<?= site_url('accounting/ledger') ?>/' + this.value + '?<?= $qs ?>'" class="input" data-select><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (int) $a['id'] === (int) $acc['id'] ? 'selected' : '' ?>><?= esc($a['code'] . ' ' . $a['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="input"></div>
    <button class="btn-primary">Show</button>
</form>
<div class="mb-6 grid grid-cols-3 gap-4">
    <?= view('partials/kpi', ['label' => 'Opening balance', 'value' => money($opening), 'icon' => 'clock', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Movement', 'value' => money($closing - $opening), 'icon' => 'refresh', 'tone' => 'violet', 'hint' => count($lines) . ' entries']) ?>
    <?= view('partials/kpi', ['label' => 'Closing balance', 'value' => money($closing), 'icon' => 'check', 'tone' => 'crimson']) ?>
</div>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Date</th><th>Journal</th><th>Description</th><th>Customer / supplier</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
    <tbody>
        <tr class="bg-slate-50 dark:bg-white/5"><td><?= fdate($from) ?></td><td></td><td class="font-semibold">Opening balance</td><td></td><td></td><td></td><td class="text-right font-semibold"><?= money($opening) ?></td></tr>
        <?php foreach ($lines as $l): ?>
            <tr><td><?= fdate($l['entry_date']) ?></td>
                <td><a href="<?= site_url('accounting/journals/' . $l['journal_entry_id']) ?>" class="font-mono text-xs font-semibold text-crimson-700 hover:underline dark:text-crimson-400"><?= esc($l['number']) ?></a></td>
                <td class="max-w-sm truncate"><?= esc($l['description'] ?: $l['entry_desc']) ?><?= $l['reconciled'] ? ' <span class="badge-green" title="Reconciled">' . icon('check', 'h-3 w-3') . '</span>' : '' ?></td>
                <td class="text-slate-500"><?= esc($l['party']) ?></td>
                <td class="text-right"><?= (float) $l['debit'] ? money($l['debit']) : '' ?></td><td class="text-right"><?= (float) $l['credit'] ? money($l['credit']) : '' ?></td>
                <td class="text-right font-semibold"><?= money($l['balance']) ?></td></tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="border-t-2 border-navy-900 dark:border-white/30"><td colspan="4" class="px-4 py-3 font-bold">Closing balance</td><td class="px-4 py-3 text-right font-bold"><?= money(array_sum(array_column($lines, 'debit'))) ?></td><td class="px-4 py-3 text-right font-bold"><?= money(array_sum(array_column($lines, 'credit'))) ?></td><td class="px-4 py-3 text-right font-bold"><?= money($closing) ?></td></tr></tfoot>
</table></div></div>
<?= $this->endSection() ?>
