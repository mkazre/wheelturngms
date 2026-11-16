<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Stock takes<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Stock takes', 'subtitle' => 'Count your shelves (scan barcodes with your phone), then post the variances to stock and the ledger.', 'crumbs' => ['inventory' => 'Inventory']]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2"><div class="table-wrap"><table class="table">
        <thead><tr><th>Stock take</th><th>Scope</th><th>Progress</th><th>Status</th><th class="text-right">Variance</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('stock-takes/' . $r['id']) ?>'"><td class="font-semibold"><?= esc($r['number']) ?><div class="text-xs text-slate-500"><?= fdatetime($r['created_at']) ?> · <?= esc($r['first_name']) ?></div></td>
                <td><?= esc($r['category'] ?: 'All stock') ?></td><td><?= (int) $r['counted'] ?>/<?= (int) $r['lines'] ?> counted</td>
                <td><span class="<?= $r['status'] === 'posted' ? 'badge-green' : 'badge-amber' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td class="text-right font-semibold <?= (float) $r['variance_value'] < 0 ? 'text-red-600' : '' ?>"><?= $r['status'] === 'posted' ? money($r['variance_value']) : '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="py-10 text-center text-slate-500">No stock takes yet.</td></tr><?php endif; ?></tbody>
    </table></div></div>
    <form method="post" action="<?= site_url('stock-takes/new') ?>" class="card h-fit"><?= csrf_field() ?>
        <div class="card-header"><div class="card-title">Start a stock take</div></div>
        <div class="card-body space-y-3">
            <div><label class="label">Scope</label><select name="category" class="input"><option value="">All stocked items</option><?php foreach ($categories as $c): ?><option><?= esc($c) ?></option><?php endforeach; ?></select></div>
            <input name="notes" placeholder="Notes (e.g. Month-end count)" class="input">
            <button class="btn-primary w-full">Start counting</button>
            <p class="text-xs text-slate-500">System quantities are snapshotted now. Tip: count after hours so no stock moves during the count.</p>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
