<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>VAT Returns<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'VAT returns (VAT201)', 'subtitle' => 'Calculated from the ledger: invoices, credit notes, supplier bills and expenses. Submit on SARS eFiling, then record it here.', 'crumbs' => ['accounting' => 'Accounting']]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <form method="post" action="<?= site_url('accounting/vat/create') ?>" class="card xl:col-span-2" x-data="{ s: '<?= $nextStart ?>', e: '<?= $nextEnd ?>' }"><?= csrf_field() ?>
        <div class="card-header"><div class="card-title">Next return: <?= fdate($nextStart) ?> – <?= fdate($nextEnd) ?></div><?= $nextEnd < date('Y-m-d') ? '<span class="badge-amber">Ready to file</span>' : '<span class="badge-gray">Period still open</span>' ?></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>VAT201 field</th><th>Description</th><th class="text-right">Excl. VAT</th><th class="text-right">VAT</th></tr></thead>
            <tbody>
                <tr><td class="font-mono">1 / 4</td><td>Standard-rated supplies (net of credit notes)</td><td class="text-right"><?= money($preview['output_base']) ?></td><td class="text-right font-semibold"><?= money($preview['output_vat']) ?></td></tr>
                <tr><td class="font-mono">14 / 15</td><td>Input tax: goods &amp; services acquired</td><td class="text-right"><?= money($preview['input_base']) ?></td><td class="text-right font-semibold"><?= money($preview['input_vat']) ?></td></tr>
            </tbody>
            <tfoot><tr class="border-t-2 border-navy-900 dark:border-white/30"><td class="px-4 py-3 font-mono font-bold">20</td><td class="px-4 py-3 font-bold"><?= $preview['net_vat'] >= 0 ? 'VAT payable to SARS' : 'VAT refundable by SARS' ?></td><td></td><td class="px-4 py-3 text-right text-lg font-bold"><?= money(abs($preview['net_vat'])) ?></td></tr></tfoot>
        </table></div>
        <div class="grid gap-3 border-t border-slate-100 p-5 sm:grid-cols-4 sm:items-end dark:border-white/10">
            <div><label class="label">Period start</label><input type="date" name="period_start" x-model="s" class="input"></div>
            <div><label class="label">Period end</label><input type="date" name="period_end" x-model="e" class="input"></div>
            <div><label class="label">SARS reference</label><input name="sars_reference" class="input" placeholder="PRN"></div>
            <button class="btn-primary" <?= $nextEnd >= date('Y-m-d') ? 'disabled title="Period has not ended"' : '' ?>>Record as filed</button>
        </div>
        <p class="px-5 pb-5 text-xs text-slate-500">Recording a return moves output and input VAT into the VAT control account (2110). Tip: lock the books to the period end afterwards.</p>
    </form>
    <div class="card"><div class="card-header"><div class="card-title">VAT cycle</div></div><div class="card-body space-y-2 text-sm text-slate-600 dark:text-slate-300">
        <p>Your VAT period is every <b><?= (int) setting('vat_period_months', 2) ?> month(s)</b> (Settings → Tax &amp; finance).</p>
        <p>SARS eFiling due date: by the <b>last business day</b> of the month after the period ends.</p>
        <a href="<?= site_url('reports/vat') ?>" class="btn-light btn-sm mt-2">Detailed VAT report →</a>
    </div></div>
</div>
<div class="card mt-6"><div class="card-header"><div class="card-title">Filed returns</div></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Period</th><th class="text-right">Output VAT</th><th class="text-right">Input VAT</th><th class="text-right">Net</th><th>Status</th><th>Payment</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td class="font-semibold"><?= fdate($r['period_start']) ?> – <?= fdate($r['period_end']) ?><div class="text-xs text-slate-500"><?= esc($r['sars_reference']) ?></div></td>
                <td class="text-right"><?= money($r['output_vat']) ?></td><td class="text-right"><?= money($r['input_vat']) ?></td><td class="text-right font-bold"><?= money($r['net_vat']) ?></td>
                <td><span class="<?= $r['status'] === 'paid' ? 'badge-green' : 'badge-amber' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><?php if ($r['status'] === 'submitted'): ?>
                    <form method="post" action="<?= site_url('accounting/vat/' . $r['id'] . '/pay') ?>" class="flex gap-1"><?= csrf_field() ?>
                        <select name="paid_from_id" class="input w-36 py-1 text-xs"><?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>"><?= esc($b['name']) ?></option><?php endforeach; ?></select>
                        <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>" class="input w-36 py-1 text-xs"><button class="btn-dark btn-sm"><?= $r['net_vat'] >= 0 ? 'Paid SARS' : 'Refund received' ?></button>
                    </form>
                <?php else: ?><span class="text-xs text-slate-500"><?= fdate($r['paid_at']) ?></span><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="py-8 text-center text-slate-500">No VAT returns recorded yet.</td></tr><?php endif; ?></tbody>
    </table></div>
</div>
<?= $this->endSection() ?>
