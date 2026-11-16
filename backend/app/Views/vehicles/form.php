<?php $isEdit = !empty($v['id']); $val = static fn ($k, $d = '') => esc(old($k, $v[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit vehicle' : 'Add vehicle' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit ' . esc($v['registration']) : 'Add vehicle', 'crumbs' => ['vehicles' => 'Vehicles']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'vehicles/' . $v['id'] . '/save' : 'vehicles/save') ?>" class="grid gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= esc(service('request')->getGet('return_to')) ?>">
    <div class="space-y-6 lg:col-span-2">
        <div class="card"><div class="card-header"><div class="card-title">Vehicle</div></div><div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Owner *</label>
                <select name="customer_id" data-select data-remote="<?= site_url('customers/lookup') ?>" required class="input">
                    <?php if ($customer): ?><option value="<?= $customer['id'] ?>" selected><?= esc(customer_name($customer)) ?></option><?php endif; ?>
                </select>
                <p class="mt-1 text-xs text-slate-500">Not listed? <a class="font-semibold text-crimson-700 dark:text-crimson-400" href="<?= site_url('customers/new?return_to=vehicles/new') ?>">Create a customer</a></p>
            </div>
            <div><label class="label">Registration *</label><input name="registration" required value="<?= $val('registration') ?>" class="input font-mono uppercase" placeholder="CA 123-456"></div>
            <div x-data="{ busy: false, async decode() { const vin = this.$refs.vin.value.trim(); if (vin.length !== 17) { toast('Enter the full 17-character VIN', 'error'); return; } this.busy = true;
                    try { const r = await fetch(WT_BASE + 'vehicles/decode-vin?vin=' + encodeURIComponent(vin), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const d = await r.json(); if (!r.ok) throw new Error(d.error);
                        ['make', 'model', 'year', 'variant', 'engine_size'].forEach(k => { const el = document.querySelector('[name=' + k + ']'); if (el && d[k] && !el.value) el.value = d[k]; });
                        ['fuel_type', 'transmission'].forEach(k => { const el = document.querySelector('[name=' + k + ']'); if (el && d[k]) el.value = d[k]; });
                        toast('Decoded: ' + [d.year, d.make, d.model].filter(Boolean).join(' ')); } catch (e) { toast(e.message, 'error'); } this.busy = false; } }">
                <label class="label">VIN</label>
                <div class="flex gap-2"><input x-ref="vin" name="vin" maxlength="17" value="<?= $val('vin') ?>" class="input font-mono uppercase"><button type="button" @click="decode()" :disabled="busy" class="btn-light shrink-0"><span x-show="!busy">Decode</span><span x-show="busy" x-cloak>…</span></button></div>
            </div>
            <div><label class="label">Make</label><input name="make" list="makes" value="<?= $val('make') ?>" class="input"><datalist id="makes"><?php foreach ($makes as $m): ?><option value="<?= $m ?>"><?php endforeach; ?></datalist></div>
            <div><label class="label">Model</label><input name="model" value="<?= $val('model') ?>" class="input" placeholder="Hilux, Polo, Ranger…"></div>
            <div><label class="label">Variant</label><input name="variant" value="<?= $val('variant') ?>" class="input" placeholder="2.8 GD-6 Raider D/C"></div>
            <div class="grid grid-cols-2 gap-4"><div><label class="label">Year</label><input name="year" type="number" min="1950" max="<?= date('Y') + 1 ?>" value="<?= $val('year') ?>" class="input"></div><div><label class="label">Colour</label><input name="colour" value="<?= $val('colour') ?>" class="input"></div></div>
            <div><label class="label">Engine number</label><input name="engine_number" value="<?= $val('engine_number') ?>" class="input font-mono uppercase"></div>
            <div><label class="label">Engine size</label><input name="engine_size" value="<?= $val('engine_size') ?>" class="input" placeholder="2.0L"></div>
            <div><label class="label">Fuel</label><select name="fuel_type" class="input"><option value="">—</option><?php foreach (['Petrol', 'Diesel', 'Hybrid', 'Electric', 'LPG'] as $f): ?><option <?= ($v['fuel_type'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Transmission</label><select name="transmission" class="input"><option value="">—</option><?php foreach (['Manual', 'Automatic', 'CVT', 'DSG'] as $f): ?><option <?= ($v['transmission'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></div>
        </div></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Service &amp; compliance</div></div><div class="card-body space-y-4">
            <div><label class="label">Current odometer (km)</label><input name="odometer" type="number" value="<?= $val('odometer') ?>" class="input"></div>
            <div><label class="label">Next service date</label><input name="next_service_date" type="date" value="<?= $val('next_service_date') ?>" class="input"></div>
            <div><label class="label">Next service at (km)</label><input name="next_service_km" type="number" value="<?= $val('next_service_km') ?>" class="input"></div>
            <div><label class="label">Licence disc expiry</label><input name="licence_expiry" type="date" value="<?= $val('licence_expiry') ?>" class="input"></div>
            <div><label class="label">Notes</label><textarea name="notes" rows="3" class="input"><?= $val('notes') ?></textarea></div>
        </div></div>
        <div class="flex gap-2"><button class="btn-primary flex-1"><?= icon('check', 'h-4 w-4') ?> Save vehicle</button><a href="<?= site_url('vehicles') ?>" class="btn-light">Cancel</a></div>
    </div>
</form>
<?= $this->endSection() ?>
