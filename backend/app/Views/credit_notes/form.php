<?php $vatOn = (int) setting('vat_registered', 1) === 1; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>New credit note<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Credit note against ' . esc($inv['number']), 'subtitle' => esc(customer_name($customer)) . ' · invoice total ' . money($inv['total']) . ' · still creditable ' . money($creditable), 'crumbs' => ['invoices' => 'Invoices', 'invoices/' . $inv['id'] => $inv['number']]]) ?>
<form method="post" action="<?= site_url('credit-notes/save') ?>" class="grid gap-6 xl:grid-cols-3"
      x-data="{ lines: <?= esc(json_encode(array_map(static fn ($l) => ['include' => false, 'quantity' => (float) $l['quantity'], 'max' => (float) $l['quantity'], 'unit_price' => (float) $l['unit_price'], 'disc' => (float) $l['discount_percent'], 'vat' => $vatOn ? (float) $l['vat_rate'] : 0], $lines)), 'attr') ?>,
                net(l) { const s = l.quantity * l.unit_price; return s - s * l.disc / 100 }, get total() { return this.lines.filter(l => l.include).reduce((a, l) => a + this.net(l) * (1 + l.vat / 100), 0) },
                all(v) { this.lines.forEach(l => l.include = v) } }">
    <?= csrf_field() ?>
    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
    <div class="card xl:col-span-2">
        <div class="card-header"><div class="card-title">Select lines to credit</div><div class="flex gap-2"><button type="button" class="btn-light btn-sm" @click="all(true)">Select all</button><button type="button" class="btn-ghost btn-sm" @click="all(false)">Clear</button></div></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th></th><th>Description</th><th class="text-right">Qty to credit</th><th class="text-right">Unit</th><th class="text-right">Credit (incl.)</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $i => $l): ?>
                <tr>
                    <td><input type="checkbox" name="items[<?= $i ?>][include]" value="1" x-model="lines[<?= $i ?>].include" class="rounded text-crimson-600 focus:ring-crimson-500"></td>
                    <td class="whitespace-normal"><?= esc($l['description']) ?>
                        <?php foreach (['item_type', 'product_id', 'description', 'unit_price', 'unit_cost', 'discount_percent', 'vat_rate'] as $f): ?><input type="hidden" name="items[<?= $i ?>][<?= $f ?>]" value="<?= esc($l[$f]) ?>"><?php endforeach; ?>
                    </td>
                    <td class="text-right"><input type="number" step="0.01" min="0" :max="lines[<?= $i ?>].max" name="items[<?= $i ?>][quantity]" x-model.number="lines[<?= $i ?>].quantity" :disabled="!lines[<?= $i ?>].include" class="input w-24 py-1 text-right"></td>
                    <td class="text-right"><?= money($l['unit_price']) ?></td>
                    <td class="text-right font-semibold" x-text="lines[<?= $i ?>].include ? money(net(lines[<?= $i ?>]) * (1 + lines[<?= $i ?>].vat / 100)) : '—'"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-body space-y-4">
            <div><label class="label">Credit note date</label><input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" class="input"></div>
            <div><label class="label">Reason *</label><textarea name="reason" rows="3" required class="input" placeholder="e.g. Part returned unused / pricing error / goodwill"><?= esc(old('reason')) ?></textarea></div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="restock" value="1" class="mt-0.5 rounded text-crimson-600 focus:ring-crimson-500"> Return credited parts to stock</label>
        </div></div>
        <div class="card overflow-hidden"><div class="bg-gradient-to-br from-navy-900 to-navy-700 p-5 text-white"><div class="text-xs uppercase tracking-wider text-slate-300">Credit total (incl. VAT)</div><div class="text-3xl font-extrabold" x-text="money(total)"></div><div class="text-xs text-slate-300">Max <?= money($creditable) ?></div></div></div>
        <button class="btn-primary w-full py-3" :disabled="total <= 0 || total > <?= $creditable + 0.009 ?>"><?= icon('check', 'h-4 w-4') ?> Issue credit note</button>
    </div>
</form>
<?= $this->endSection() ?>
