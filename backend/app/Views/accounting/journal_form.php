<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Manual journal<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Manual journal', 'subtitle' => 'For adjustments, depreciation, accruals, payroll, loans and opening balances. Debits must equal credits.', 'crumbs' => ['accounting' => 'Accounting', 'accounting/journals' => 'Journals']]) ?>
<form method="post" enctype="multipart/form-data" action="<?= site_url('accounting/journals/save') ?>" x-data="{ lines: [{ a: '', d: '', c: '', t: '' }, { a: '', d: '', c: '', t: '' }],
        get dr() { return this.lines.reduce((s, l) => s + (parseFloat(l.d) || 0), 0) }, get cr() { return this.lines.reduce((s, l) => s + (parseFloat(l.c) || 0), 0) },
        get ok() { return this.dr > 0 && Math.abs(this.dr - this.cr) < 0.005 } }" class="space-y-6">
    <?= csrf_field() ?>
    <div class="card"><div class="card-body grid gap-4 sm:grid-cols-4">
        <div><label class="label">Date</label><input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" class="input" required></div>
        <div class="sm:col-span-2"><label class="label">Description</label><input name="description" required class="input" placeholder="e.g. Monthly depreciation – workshop equipment"></div>
        <div><label class="label">Reference</label><input name="reference" class="input"></div>
    </div></div>
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th class="w-2/5">Account</th><th>Line description</th><th class="w-36 text-right">Debit</th><th class="w-36 text-right">Credit</th><th class="w-10"></th></tr></thead>
            <tbody>
                <template x-for="(l, i) in lines" :key="i"><tr>
                    <td><select :name="`lines[${i}][account_id]`" x-model="l.a" class="input py-1.5 text-sm" required><option value="">Select account…</option><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?></select></td>
                    <td><input :name="`lines[${i}][description]`" x-model="l.t" class="input py-1.5 text-sm"></td>
                    <td><input type="number" step="0.01" min="0" :name="`lines[${i}][debit]`" x-model="l.d" @input="if (l.d) l.c = ''" class="input py-1.5 text-right text-sm"></td>
                    <td><input type="number" step="0.01" min="0" :name="`lines[${i}][credit]`" x-model="l.c" @input="if (l.c) l.d = ''" class="input py-1.5 text-right text-sm"></td>
                    <td><button type="button" @click="lines.length > 2 && lines.splice(i, 1)" class="text-slate-400 hover:text-red-600"><?= icon('x', 'h-4 w-4') ?></button></td>
                </tr></template>
            </tbody>
            <tfoot><tr>
                <td><button type="button" @click="lines.push({ a: '', d: '', c: '', t: '' })" class="btn-light btn-sm"><?= icon('plus', 'h-3.5 w-3.5') ?> Add line</button></td>
                <td class="px-4 text-right text-sm font-semibold">Totals</td>
                <td class="px-4 py-3 text-right font-bold" x-text="money(dr)"></td><td class="px-4 py-3 text-right font-bold" x-text="money(cr)"></td><td></td>
            </tr></tfoot>
        </table></div>
        <div class="flex flex-col gap-3 border-t border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
            <div class="text-sm"><span x-show="ok" class="badge-green">Balanced</span><span x-show="!ok" class="badge-red" x-text="'Out of balance by ' + money(Math.abs(dr - cr))"></span></div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center"><input type="file" name="document" class="text-xs text-slate-500"><button class="btn-primary" :disabled="!ok"><?= icon('check', 'h-4 w-4') ?> Post journal</button></div>
        </div>
    </div>
    <div class="card"><div class="card-body"><label class="label">Internal memo (why this journal was needed)</label><textarea name="memo" rows="2" class="input"></textarea></div></div>
</form>
<?= $this->endSection() ?>
