<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Expenses<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Expenses &amp; slips', 'subtitle' => fdate($from) . ' – ' . fdate($to) . ' · snap a slip, VAT is calculated and posted to the ledger automatically.',
    'actions' => view('partials/export_buttons', ['base' => 'expenses']) . '<a href="' . site_url('expenses/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Capture expense</a>']) ?>
<div class="mb-6 grid gap-6 lg:grid-cols-3">
    <div class="grid grid-cols-2 gap-4 lg:col-span-2">
        <?= view('partials/kpi', ['label' => 'Total spend', 'value' => money($totals['total']), 'icon' => 'receipt', 'tone' => 'crimson', 'hint' => $totals['count'] . ' expenses']) ?>
        <?= view('partials/kpi', ['label' => 'Input VAT claimable', 'value' => money($totals['vat']), 'icon' => 'shield', 'tone' => 'green', 'hint' => 'for VAT201']) ?>
        <?= view('partials/kpi', ['label' => 'Excl. VAT', 'value' => money($totals['excl']), 'icon' => 'wallet', 'tone' => 'navy']) ?>
        <?= view('partials/kpi', ['label' => 'Largest category', 'value' => esc(array_key_first($byAccount) ?? '—'), 'icon' => 'pie', 'tone' => 'violet', 'hint' => $byAccount ? money(reset($byAccount)) : '']) ?>
    </div>
    <div class="card"><div class="card-header"><div class="card-title">By category</div></div><div class="card-body">
        <?php if ($byAccount): ?><div class="h-48"><canvas id="expChart"></canvas></div><?php else: ?><p class="py-10 text-center text-sm text-slate-500">No expenses in this period.</p><?php endif; ?>
    </div></div>
</div>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search description, payee, supplier or reference…" class="input flex-1">
        <select name="account_id" class="input sm:w-56"><option value="">All categories</option><?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= (int) service('request')->getGet('account_id') === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['code'] . ' ' . $a['name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= esc($from) ?>" class="input sm:w-40"><input type="date" name="to" value="<?= esc($to) ?>" class="input sm:w-40">
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Paid to</th><th>Description</th><th>Category</th><th class="text-center">Slip</th><th class="text-right">VAT</th><th class="text-right">Total</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('expenses/' . $r['id'] . '/edit') ?>'">
                <td><div class="font-medium"><?= fdate($r['expense_date']) ?></div><div class="text-xs text-slate-500"><?= esc($r['number']) ?></div></td>
                <td><?= esc($r['supplier_name'] ?: $r['payee'] ?: '—') ?></td>
                <td class="max-w-xs truncate"><?= esc($r['description']) ?></td>
                <td><span class="badge-gray"><?= esc($r['account_name']) ?></span></td>
                <td class="text-center"><?= !empty($slips[$r['id']]) ? '<span class="badge-green">' . icon('paperclip', 'h-3 w-3') . ' ' . $slips[$r['id']] . '</span>' : '<span class="text-xs text-amber-600">missing</span>' ?></td>
                <td class="text-right text-slate-500"><?= money($r['vat_amount']) ?></td>
                <td class="text-right font-semibold"><?= money($r['total']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'receipt', 'title' => 'No expenses in this period', 'action' => '<a href="' . site_url('expenses/new') . '" class="btn-primary">Capture an expense</a>']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<?php if ($byAccount): ?>
<script src="<?= base_url('assets/vendor/chart.umd.js') ?>"></script>
<script>
wtChartDefaults();
const ea = <?= json_encode(array_slice($byAccount, 0, 8, true)) ?>;
new Chart(document.getElementById('expChart'), { type: 'doughnut', data: { labels: Object.keys(ea), datasets: [{ data: Object.values(ea), backgroundColor: WT_COLORS, borderWidth: 0 }] }, options: { cutout: '65%', plugins: { legend: { position: 'right', labels: { font: { size: 10 } } }, tooltip: { callbacks: { label: c => c.label + ': ' + money(c.raw) } } } } });
</script>
<?php endif; ?>
<?= $this->endSection() ?>
