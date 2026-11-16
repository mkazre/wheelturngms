<?php use App\Libraries\LineItems; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Parts &amp; Inventory<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Parts, labour &amp; inventory', 'subtitle' => 'Your price list for labour, services and stocked parts.',
    'actions' => '<a href="' . site_url('stock/reorder') . '" class="btn-light">' . icon('truck', 'h-4 w-4') . ' Reorder</a><a href="' . site_url('stock-takes') . '" class="btn-light">' . icon('clipboard', 'h-4 w-4') . ' Stock take</a><a href="' . site_url('stock/pricing') . '" class="btn-light">' . icon('chart', 'h-4 w-4') . ' Pricing</a>' . view('partials/export_buttons', ['base' => 'inventory']) . '<a href="' . site_url('inventory/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New item</a>']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Stock value (cost)', 'value' => money_short($val['cost']), 'icon' => 'box', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Retail value', 'value' => money_short($val['retail']), 'icon' => 'chart', 'tone' => 'green']) ?>
    <?= view('partials/kpi', ['label' => 'Stocked items', 'value' => number_format($val['n']), 'icon' => 'clipboard', 'tone' => 'sky']) ?>
    <a href="?filter=low"><?= view('partials/kpi', ['label' => 'Low / out of stock', 'value' => $low, 'icon' => 'alert', 'tone' => $low ? 'crimson' : 'green']) ?></a>
</div>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input id="invSearch" name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search name, SKU, barcode, brand…" class="input flex-1" @scanned="$el.form.submit()" x-data>
        <button type="button" x-data @click="$dispatch('scan', { target: 'invSearch' })" class="btn-light" title="Scan barcode with camera"><?= icon('eye', 'h-4 w-4') ?> Scan</button>
        <select name="type" class="input sm:w-40"><option value="">All types</option><?php foreach (LineItems::TYPES as $k => $l): ?><option value="<?= $k ?>" <?= service('request')->getGet('type') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Item</th><th>Type</th><th>Supplier</th><th class="text-right">Cost</th><th class="text-right">Sell (excl.)</th><th class="text-right">Margin</th><th class="text-right">On hand</th></tr></thead>
        <tbody><?php foreach ($rows as $r): $margin = (float) $r['sell_price'] > 0 ? ((float) $r['sell_price'] - (float) $r['cost_price']) / (float) $r['sell_price'] * 100 : 0; $low = $r['track_stock'] && (float) $r['qty_on_hand'] <= (float) $r['reorder_level']; ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('inventory/' . $r['id'] . '/edit') ?>'">
                <td><div class="font-semibold"><?= esc($r['name']) ?></div><div class="text-xs text-slate-500"><?= esc(implode(' · ', array_filter([$r['sku'], $r['brand'], $r['category']]))) ?></div></td>
                <td><span class="<?= $r['type'] === 'labour' ? 'badge-navy' : 'badge-gray' ?>"><?= esc(LineItems::TYPES[$r['type']] ?? $r['type']) ?></span></td>
                <td class="text-slate-500"><?= esc($r['supplier_name'] ?: '—') ?></td>
                <td class="text-right"><?= money($r['cost_price']) ?></td>
                <td class="text-right font-semibold"><?= money($r['sell_price']) ?></td>
                <td class="text-right <?= $margin < 20 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= number_format($margin, 0) ?>%</td>
                <td class="text-right"><?= $r['track_stock'] ? '<span class="' . ($low ? 'badge-red' : 'badge-green') . '">' . (float) $r['qty_on_hand'] . ' ' . esc($r['unit']) . '</span>' : '<span class="text-xs text-slate-400">n/a</span>' ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'box', 'title' => 'No items found', 'text' => 'Add labour rates, services and parts to speed up quoting.', 'action' => '<a href="' . site_url('inventory/new') . '" class="btn-primary">Add item</a>']) ?><?php endif; ?>
</div>
<?= view('partials/scanner') ?>
<?= $this->endSection() ?>
