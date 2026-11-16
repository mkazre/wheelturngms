<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Chart of Accounts<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div x-data="{ edit: null, blank: { id: '', code: '', name: '', type: 'expense', sub_type: 'operating', description: '', bank_account_number: '', is_active: 1, is_system: 0 } }">
<?= view('partials/page_header', ['title' => 'Chart of accounts', 'subtitle' => 'South African chart (IFRS for SMEs). System accounts drive automatic postings and can only be renamed.', 'crumbs' => ['accounting' => 'Accounting'],
    'actions' => '<button type="button" @click="edit = { ...blank }" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New account</button>']) ?>
<?php foreach ($types as $type => $label): $list = array_filter($rows, static fn ($r) => $r['type'] === $type); ?>
    <div class="card mb-6">
        <div class="card-header"><div class="card-title"><?= $label ?>s</div><span class="text-sm font-semibold"><?= money(array_sum(array_column($list, 'balance'))) ?></span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th class="w-24">Code</th><th>Account</th><th>Type</th><th class="text-center">Entries</th><th class="text-right">Balance</th><th></th></tr></thead>
            <tbody><?php foreach ($list as $a): ?>
                <tr class="<?= $a['is_active'] ? '' : 'opacity-50' ?>">
                    <td class="font-mono font-semibold"><?= esc($a['code']) ?></td>
                    <td><a href="<?= site_url('accounting/ledger/' . $a['id']) ?>" class="font-semibold hover:text-crimson-700"><?= esc($a['name']) ?></a><?= $a['is_system'] ? ' <span class="badge-gray">system</span>' : '' ?><?php if ($a['description']): ?><div class="text-xs text-slate-500"><?= esc($a['description']) ?></div><?php endif; ?></td>
                    <td class="text-slate-500"><?= esc($subtypes[$a['type']][$a['sub_type']] ?? $a['sub_type']) ?></td>
                    <td class="text-center"><span class="badge-gray"><?= (int) $a['lines'] ?></span></td>
                    <td class="text-right font-semibold"><?= money($a['balance']) ?></td>
                    <td class="text-right"><button type="button" class="btn-ghost btn-sm" @click='edit = <?= esc(json_encode(array_intersect_key($a, array_flip(['id', 'code', 'name', 'type', 'sub_type', 'description', 'bank_account_number', 'is_active', 'is_system']))), 'attr') ?>'>Edit</button></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
<?php endforeach; ?>

<!-- Edit drawer -->
<div x-show="edit" x-cloak class="fixed inset-0 z-50 flex justify-end bg-navy-950/50 backdrop-blur-sm" @keydown.escape.window="edit = null">
    <form method="post" action="<?= site_url('accounting/accounts/save') ?>" @click.outside="edit = null" class="h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl dark:bg-navy-900" x-show="edit" x-transition>
        <?= csrf_field() ?>
        <template x-if="edit"><div class="space-y-4">
            <input type="hidden" name="id" :value="edit.id">
            <h2 class="text-lg font-bold" x-text="edit.id ? 'Edit account' : 'New account'"></h2>
            <p x-show="edit.is_system == 1" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">System account: only the name, description and bank number can change.</p>
            <div class="grid grid-cols-3 gap-3"><div><label class="label">Code</label><input name="code" x-model="edit.code" :readonly="edit.is_system == 1" class="input font-mono" required></div><div class="col-span-2"><label class="label">Name</label><input name="name" x-model="edit.name" class="input" required></div></div>
            <div><label class="label">Type</label><select name="type" x-model="edit.type" :disabled="edit.is_system == 1" class="input"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Detail type</label>
                <?php foreach ($subtypes as $t => $subs): ?>
                    <select name="sub_type" x-show="edit.type === '<?= $t ?>'" :disabled="edit.type !== '<?= $t ?>' || edit.is_system == 1" x-model="edit.sub_type" class="input"><?php foreach ($subs as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
                <?php endforeach; ?>
            </div>
            <div x-show="edit.sub_type === 'bank'"><label class="label">Bank account number</label><input name="bank_account_number" x-model="edit.bank_account_number" class="input"></div>
            <div><label class="label">Description</label><input name="description" x-model="edit.description" class="input"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" :checked="edit.is_active == 1" class="rounded text-crimson-600"> Active</label>
            <div class="flex gap-2 pt-2"><button class="btn-primary flex-1">Save account</button><button type="button" @click="edit = null" class="btn-light">Cancel</button></div>
        </div></template>
    </form>
</div>
</div>
<?= $this->endSection() ?>
