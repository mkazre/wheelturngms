<?php $open = $t['status'] === 'counting'; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Stock take <?= esc($t['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Stock take ' . esc($t['number']) . ' <span class="' . ($open ? 'badge-amber' : 'badge-green') . '">' . ($open ? 'Counting' : 'Posted') . '</span>', 'subtitle' => ($t['category'] ? esc($t['category']) . ' · ' : 'All stock · ') . count($lines) . ' items' . (!$open ? ' · variance ' . money($t['variance_value']) : ''), 'crumbs' => ['stock-takes' => 'Stock takes']]) ?>
<form method="post" action="<?= site_url('stock-takes/' . $t['id'] . '/save') ?>" x-data="{ q: '', counts: {}, focusCode(code) { const row = document.querySelector('[data-code=&quot;' + code.toLowerCase() + '&quot;]'); if (row) { row.scrollIntoView({ block: 'center' }); const i = row.querySelector('input'); i.focus(); i.select(); toast('Found: ' + row.dataset.name); } else { toast('No item with code ' + code, 'error'); } } }"><?= csrf_field() ?>
    <?php if ($open): ?>
    <div class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row">
        <input id="stScan" x-model="q" @keydown.enter.prevent="focusCode(q); q = ''" @scanned="focusCode($event.detail); q = ''" placeholder="Scan or type barcode / SKU then Enter…" class="input flex-1">
        <button type="button" @click="$dispatch('scan', { target: 'stScan' })" class="btn-dark"><?= icon('eye', 'h-4 w-4') ?> Camera scan</button>
    </div>
    <?php endif; ?>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Bin</th><th>Item</th><th class="text-right">System qty</th><th class="text-right w-36">Counted</th><th class="text-right">Variance</th><th class="text-right">Value</th></tr></thead>
        <tbody><?php foreach ($lines as $l): $var = $l['counted_qty'] !== null ? (float) $l['counted_qty'] - (float) $l['system_qty'] : null; ?>
            <tr data-code="<?= esc(strtolower((string) ($l['barcode'] ?: $l['sku'])), 'attr') ?>" data-name="<?= esc($l['name'], 'attr') ?>" x-data="{ c: <?= $l['counted_qty'] === null ? "''" : (float) $l['counted_qty'] ?>, sys: <?= (float) $l['system_qty'] ?> }">
                <td class="font-mono text-xs"><?= esc($l['bin_location'] ?: '—') ?></td>
                <td><div class="font-semibold"><?= esc($l['name']) ?></div><div class="text-xs text-slate-500"><?= esc($l['sku']) ?></div></td>
                <td class="text-right"><?= (float) $l['system_qty'] ?></td>
                <td class="text-right"><input name="count[<?= $l['id'] ?>]" type="number" step="0.001" x-model="c" <?= $open ? '' : 'disabled' ?> class="input w-28 py-1 text-right"></td>
                <td class="text-right font-semibold" :class="c !== '' && c - sys < 0 ? 'text-red-600' : (c !== '' && c - sys > 0 ? 'text-emerald-600' : 'text-slate-400')" x-text="c === '' ? '—' : (c - sys > 0 ? '+' : '') + Math.round((c - sys) * 1000) / 1000"></td>
                <td class="text-right text-slate-500" x-text="c === '' ? '' : money((c - sys) * <?= (float) $l['unit_cost'] ?>)"></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if ($open): ?>
    <div class="flex flex-col gap-2 border-t border-slate-100 p-5 sm:flex-row sm:justify-end dark:border-white/10">
        <button class="btn-light">Save counts</button>
        <button name="post" value="1" class="btn-primary" onclick="return confirm('Post this stock take? Stock levels will be set to the counted quantities and the variance booked to cost of sales.')">Post variances</button>
    </div>
    <?php endif; ?>
    </div>
</form>
<?= view('partials/scanner') ?>
<?= $this->endSection() ?>
