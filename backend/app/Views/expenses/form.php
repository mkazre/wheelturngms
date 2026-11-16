<?php $isEdit = !empty($e['id']); $val = static fn ($k, $d = '') => esc(old($k, $e[$k] ?? $d)); $rate = (float) setting('vat_rate', 15); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit expense' : 'Capture expense' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit expense ' . esc($e['number']) : 'Capture expense', 'subtitle' => 'Enter the slip total incl. VAT. We split out the VAT for your VAT201.', 'crumbs' => ['expenses' => 'Expenses']]) ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url($isEdit ? 'expenses/' . $e['id'] . '/save' : 'expenses/save') ?>" class="grid gap-6 lg:grid-cols-3"
      x-data="{ total: <?= (float) ($e['total'] ?? 0) ?: "''" ?>, claim: <?= !empty($e['is_vat_claimable']) ? 'true' : 'false' ?>, preview: null, get vat() { return this.claim ? Math.round((this.total || 0) * <?= $rate ?> / <?= 100 + $rate ?> * 100) / 100 : 0 } }">
    <?= csrf_field() ?>
    <div class="space-y-6 lg:col-span-2">
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-2">
            <div><label class="label">Date *</label><input type="date" name="expense_date" value="<?= $val('expense_date') ?>" required class="input"></div>
            <div><label class="label">Category (ledger account) *</label><select name="account_id" required data-select class="input"><option value="">Select…</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (int) old('account_id', $e['account_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div><label class="label">Supplier</label><select name="supplier_id" data-select class="input"><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int) ($e['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">…or payee</label><input name="payee" value="<?= $val('payee') ?>" class="input" placeholder="e.g. Engen Garage"></div>
            <div class="sm:col-span-2"><label class="label">Description *</label><input name="description" value="<?= $val('description') ?>" required class="input" placeholder="e.g. Diesel for courier bakkie"></div>
            <div><label class="label">Total paid (incl. VAT) *</label><input name="total" type="number" step="0.01" min="0.01" x-model.number="total" required class="input text-lg font-semibold"></div>
            <div><label class="label">VAT amount</label><input name="vat_amount" type="number" step="0.01" :value="vat.toFixed(2)" :disabled="!claim" class="input"><p class="mt-1 text-xs text-slate-500">Auto-calculated @ <?= $rate ?>%; edit to match the slip.</p></div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_vat_claimable" value="1" x-model="claim" class="rounded text-crimson-600 focus:ring-crimson-500"> Valid tax invoice from a VAT vendor (input VAT is claimable)</label>
        </div></div>
        <div class="card"><div class="card-body grid gap-4 sm:grid-cols-3">
            <div><label class="label">Paid from</label><select name="paid_from_id" class="input"><?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= (int) ($e['paid_from_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Method</label><select name="payment_method" class="input"><?php foreach (['eft' => 'EFT', 'card' => 'Card', 'cash' => 'Cash', 'debit_order' => 'Debit order', 'account' => 'On account'] as $k => $l): ?><option value="<?= $k ?>" <?= ($e['payment_method'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Reference / slip no.</label><input name="reference" value="<?= $val('reference') ?>" class="input"></div>
        </div></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Slip / receipt</div></div><div class="card-body">
            <label class="flex cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 text-center hover:border-crimson-400 dark:border-white/15" :class="preview ? 'p-1' : 'px-6 py-10'">
                <input type="file" name="slip" accept="image/*,.pdf" capture="environment" class="sr-only" @change="const f = $event.target.files[0]; preview = f && f.type.startsWith('image/') ? URL.createObjectURL(f) : (f ? 'pdf' : null)">
                <template x-if="preview && preview !== 'pdf'"><img :src="preview" class="max-h-72 rounded-xl"></template>
                <template x-if="preview === 'pdf'"><span class="py-8 text-sm font-semibold">PDF selected</span></template>
                <template x-if="!preview"><div><span class="mx-auto mb-2 block w-fit text-crimson-600"><?= icon('upload', 'h-8 w-8') ?></span><span class="text-sm font-semibold">Snap or upload the slip</span><span class="mt-1 block text-xs text-slate-500">Keep slips for 5 years (SARS)</span></div></template>
            </label>
            <?php foreach ($files as $f): ?><a href="<?= site_url('attachments/' . $f['id']) ?>" target="_blank" class="mt-2 flex items-center gap-2 text-xs font-semibold text-crimson-700"><?= icon('paperclip', 'h-3.5 w-3.5') ?> <?= esc($f['original_name']) ?></a><?php endforeach; ?>
        </div></div>
        <div class="card bg-slate-50 dark:bg-white/5"><dl class="card-body space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Excl. VAT</dt><dd x-text="money((total || 0) - vat)"></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">VAT</dt><dd x-text="money(vat)"></dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-1 font-bold dark:border-white/10"><dt>Total</dt><dd x-text="money(total || 0)"></dd></div>
        </dl></div>
        <div class="space-y-2">
            <button class="btn-primary w-full py-3"><?= icon('check', 'h-4 w-4') ?> Save expense</button>
            <?php if (!$isEdit): ?><button name="another" value="1" class="btn-light w-full">Save &amp; capture another</button><?php endif; ?>
        </div>
        <?php if ($isEdit): ?><div class="text-right"><button form="deleteExpense" class="btn-ghost btn-sm text-red-600"><?= icon('trash', 'h-3.5 w-3.5') ?> Delete</button></div><?php endif; ?>
    </div>
</form>
<?php if ($isEdit): ?><form id="deleteExpense" method="post" action="<?= site_url('expenses/' . $e['id'] . '/delete') ?>" data-confirm="Delete this expense? The ledger entry will be reversed."><?= csrf_field() ?></form><?php endif; ?>
<?= $this->endSection() ?>
