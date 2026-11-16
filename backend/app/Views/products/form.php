<?php use App\Libraries\LineItems; $isEdit = !empty($p['id']); $val = static fn ($k, $d = '') => esc(old($k, $p[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? esc($p['name']) : 'New item' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? esc($p['name']) : 'New item', 'crumbs' => ['inventory' => 'Inventory']]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <form method="post" action="<?= site_url($isEdit ? 'inventory/' . $p['id'] . '/save' : 'inventory/save') ?>" class="space-y-6 xl:col-span-2" x-data="{ cost: <?= (float) ($p['cost_price'] ?? 0) ?>, sell: <?= (float) ($p['sell_price'] ?? 0) ?>, type: '<?= $val('type') ?>' }">
        <?= csrf_field() ?>
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Name *</label><input name="name" value="<?= $val('name') ?>" required class="input" placeholder="e.g. Brake pads – front (Hilux)"></div>
            <div><label class="label">Type *</label><select name="type" x-model="type" class="input"><?php foreach (LineItems::TYPES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Category</label><input name="category" list="cats" value="<?= $val('category') ?>" class="input"><datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?= esc($c) ?>"><?php endforeach; ?></datalist></div>
            <div><label class="label">SKU / part no.</label><input name="sku" value="<?= $val('sku') ?>" class="input font-mono"></div>
            <div><label class="label">Barcode</label><input name="barcode" value="<?= $val('barcode') ?>" class="input font-mono"></div>
            <div><label class="label">Brand</label><input name="brand" value="<?= $val('brand') ?>" class="input"></div>
            <div><label class="label">Supplier</label><select name="supplier_id" class="input"><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($p['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; ?></select></div>
            <div class="sm:col-span-2"><label class="label">Description</label><textarea name="description" rows="2" class="input"><?= $val('description') ?></textarea></div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Pricing</div><span class="text-sm">Margin: <b :class="sell > 0 && (sell - cost) / sell < .2 ? 'text-amber-600' : 'text-emerald-600'" x-text="sell > 0 ? Math.round((sell - cost) / sell * 100) + '%' : '—'"></b></span></div><div class="card-body grid gap-4 sm:grid-cols-4">
            <div><label class="label">Cost price</label><input name="cost_price" type="number" step="0.01" x-model.number="cost" class="input"></div>
            <div><label class="label">Sell price (excl. VAT) *</label><input name="sell_price" type="number" step="0.01" x-model.number="sell" required class="input"></div>
            <div><label class="label">VAT rate %</label><input name="vat_rate" type="number" step="0.01" value="<?= $val('vat_rate', 15) ?>" class="input"></div>
            <div><label class="label">Unit</label><input name="unit" value="<?= $val('unit', 'each') ?>" class="input" placeholder="each, litre, hour"></div>
            <div><label class="label">Warranty (months)</label><input name="warranty_months" type="number" min="0" value="<?= $val('warranty_months') ?>" class="input" placeholder="e.g. 12"></div>
            <div x-show="type === 'labour' || type === 'service'"><label class="label">Standard time (hours)</label><input name="labour_hours" type="number" step="0.1" min="0" value="<?= $val('labour_hours') ?>" class="input" placeholder="Labour time guide"></div>
            <div class="sm:col-span-4 flex flex-wrap gap-2 text-xs text-slate-500">Quick markup:
                <?php foreach ([25, 35, 50, 75, 100] as $m): ?><button type="button" @click="sell = Math.round(cost * <?= 1 + $m / 100 ?> * 100) / 100" class="badge-gray hover:bg-slate-100">+<?= $m ?>%</button><?php endforeach; ?>
            </div>
        </div></div>
        <div class="card" x-show="type !== 'labour' && type !== 'service'"><div class="card-header"><div class="card-title">Stock control</div></div><div class="card-body grid gap-4 sm:grid-cols-3">
            <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="track_stock" value="1" <?= !empty($p['track_stock']) ? 'checked' : '' ?> class="rounded text-crimson-600 focus:ring-crimson-500"> Track stock levels for this item</label>
            <?php if (!$isEdit): ?><div><label class="label">Opening quantity</label><input name="opening_qty" type="number" step="0.001" value="0" class="input"></div><?php endif; ?>
            <div><label class="label">Reorder level</label><input name="reorder_level" type="number" step="0.001" value="<?= $val('reorder_level', 0) ?>" class="input"></div>
            <div><label class="label">Bin location</label><input name="bin_location" value="<?= $val('bin_location') ?>" class="input" placeholder="Shelf A3"></div>
        </div></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?> class="rounded text-crimson-600 focus:ring-crimson-500"> Active (available on job cards &amp; invoices)</label>
        <div class="flex gap-2"><button class="btn-primary"><?= icon('check', 'h-4 w-4') ?> Save item</button><a href="<?= site_url('inventory') ?>" class="btn-light">Cancel</a></div>
    </form>

    <?php if ($isEdit): ?>
    <div class="space-y-6">
        <?php if ($p['track_stock']): ?>
        <div class="card overflow-hidden">
            <div class="bg-gradient-to-br from-navy-900 to-navy-700 p-5 text-white"><div class="text-xs uppercase tracking-wider text-slate-300">On hand</div><div class="text-3xl font-extrabold"><?= (float) $p['qty_on_hand'] ?> <span class="text-base font-medium"><?= esc($p['unit']) ?></span></div><div class="text-xs text-slate-300">Value <?= money((float) $p['qty_on_hand'] * (float) $p['cost_price']) ?></div></div>
            <form method="post" action="<?= site_url('inventory/' . $p['id'] . '/adjust') ?>" class="card-body space-y-3"><?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-2">
                    <select name="type" class="input"><option value="in">Receive stock</option><option value="out">Write off / use</option><option value="adjust">Adjust (±)</option></select>
                    <input name="quantity" type="number" step="0.001" placeholder="Qty" required class="input">
                </div>
                <input name="unit_cost" type="number" step="0.01" placeholder="Unit cost (optional)" class="input">
                <input name="note" placeholder="Note / supplier invoice no." class="input">
                <button class="btn-dark w-full">Update stock</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Stock movements</div></div>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-white/10">
                <?php foreach ($moves as $m): ?><li class="flex items-center justify-between px-5 py-2.5"><div><div class="font-medium"><?= esc($m['note'] ?: ucfirst($m['type']) . ($m['reference_type'] ? ' · ' . $m['reference_type'] . ' #' . $m['reference_id'] : '')) ?></div><div class="text-xs text-slate-500"><?= fdatetime($m['created_at']) ?> · <?= esc($m['first_name'] ?? 'System') ?></div></div><span class="font-semibold <?= (float) $m['quantity'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= (float) $m['quantity'] > 0 ? '+' : '' ?><?= (float) $m['quantity'] ?></span></li><?php endforeach; ?>
                <?php if (!$moves): ?><li class="px-5 py-6 text-center text-slate-500">No movements yet.</li><?php endif; ?>
            </ul>
        </div>
        <form method="post" action="<?= site_url('inventory/' . $p['id'] . '/delete') ?>" data-confirm="Archive this item?" class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Archive item</button></form>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
