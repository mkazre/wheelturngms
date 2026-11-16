<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Reorder<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Reorder stock', 'subtitle' => 'Items at or below their reorder level. Quantities top up to twice the reorder level, minus what is already on order.', 'crumbs' => ['inventory' => 'Inventory']]) ?>
<form method="post" action="<?= site_url('stock/reorder') ?>" class="card"><?= csrf_field() ?>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Item</th><th>Supplier</th><th class="text-right">On hand</th><th class="text-right">Reorder at</th><th class="text-right">On order</th><th class="text-right">Unit cost</th><th class="w-32 text-right">Order qty</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr><td><div class="font-semibold"><?= esc($r['name']) ?></div><div class="text-xs text-slate-500"><?= esc($r['sku']) ?></div></td>
                <td><?= $r['supplier_name'] ? esc($r['supplier_name']) : '<span class="badge-red">No supplier</span>' ?></td>
                <td class="text-right"><span class="<?= (float) $r['qty_on_hand'] <= 0 ? 'badge-red' : 'badge-amber' ?>"><?= (float) $r['qty_on_hand'] ?></span></td>
                <td class="text-right"><?= (float) $r['reorder_level'] ?></td><td class="text-right text-slate-500"><?= (float) $r['on_order'] ?: '—' ?></td>
                <td class="text-right"><?= money($r['cost_price']) ?></td>
                <td class="text-right"><input type="number" step="1" min="0" name="qty[<?= $r['id'] ?>]" value="<?= $r['suggested'] ?>" class="input w-24 py-1 text-right"></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <div class="flex justify-end border-t border-slate-100 p-5 dark:border-white/10"><button class="btn-primary"><?= icon('truck', 'h-4 w-4') ?> Create purchase orders</button></div>
    <?php else: ?><?= view('partials/empty', ['icon' => 'check', 'title' => 'Stock levels are healthy', 'text' => 'Nothing is at or below its reorder level.']) ?><?php endif; ?>
</form>
<?= $this->endSection() ?>
