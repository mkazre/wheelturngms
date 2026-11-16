<?php /** @var ?array $customer @var ?array $vehicle @var string $returnTo @var bool $vehicleRequired */ ?>
<div><label class="label">Customer *</label>
    <select id="customer_id" name="customer_id" data-select data-remote="<?= site_url('customers/lookup') ?>" required class="input">
        <?php if ($customer): ?><option value="<?= $customer['id'] ?>" selected><?= esc(customer_name($customer)) ?></option><?php endif; ?>
    </select>
    <a class="mt-1 inline-block text-xs font-semibold text-crimson-700 dark:text-crimson-400" href="<?= site_url('customers/new?return_to=' . $returnTo) ?>">+ New customer</a>
</div>
<div><label class="label">Vehicle <?= !empty($vehicleRequired) ? '*' : '' ?></label>
    <select id="vehicle_id" name="vehicle_id" <?= !empty($vehicleRequired) ? 'required' : '' ?> class="input">
        <?php if ($vehicle): ?><option value="<?= $vehicle['id'] ?>" selected><?= esc($vehicle['registration'] . ' · ' . $vehicle['make'] . ' ' . $vehicle['model']) ?></option><?php else: ?><option value="">Select a customer first</option><?php endif; ?>
    </select>
    <a id="newVehicleLink" class="mt-1 inline-block text-xs font-semibold text-crimson-700 dark:text-crimson-400" href="<?= site_url('vehicles/new?return_to=' . $returnTo . ($customer ? '&customer_id=' . $customer['id'] : '')) ?>">+ New vehicle</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const custEl = document.getElementById('customer_id'), vehEl = document.getElementById('vehicle_id'), odo = document.getElementById('odometer_in');
    const vehicles = new TomSelect(vehEl, { valueField: 'id', labelField: 'text', searchField: ['text'], allowEmptyOption: true });
    let list = [];
    const load = async (cid, keep) => {
        if (!cid) return;
        const r = await fetch(WT_BASE + 'vehicles/lookup?customer_id=' + cid, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        list = await r.json();
        const current = vehicles.getValue();
        vehicles.clearOptions(); vehicles.addOptions(list);
        if (keep && current) vehicles.setValue(current, true); else if (list.length === 1) vehicles.setValue(list[0].id); else vehicles.clear();
        document.getElementById('newVehicleLink').href = WT_BASE + 'vehicles/new?return_to=<?= esc($returnTo, 'js') ?>&customer_id=' + cid;
    };
    vehicles.on('change', (id) => { const v = list.find(x => String(x.id) === String(id)); if (odo && v && v.odometer && !odo.value) odo.value = v.odometer; });
    setTimeout(() => { if (custEl.tomselect) custEl.tomselect.on('change', (v) => load(v, false)); load(custEl.value, true); }, 30);
});
</script>
