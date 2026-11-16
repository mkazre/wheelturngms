<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($cn['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Credit note ' . esc($cn['number']) . ' <span class="' . ($cn['status'] === 'issued' ? 'badge-green' : 'badge-red') . '">' . ucfirst(esc($cn['status'])) . '</span>',
    'subtitle' => esc(customer_name($customer)) . ' · against invoice <a class="font-semibold text-crimson-700 dark:text-crimson-400" href="' . site_url('invoices/' . $invoice['id']) . '">' . esc($invoice['number']) . '</a>',
    'crumbs' => ['credit-notes' => 'Credit notes'],
    'actions' => '<a href="' . site_url('credit-notes/' . $cn['id'] . '/pdf') . '" target="_blank" class="btn-light">' . icon('printer', 'h-4 w-4') . ' PDF</a>']) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">VAT</th><th class="text-right">Amount (excl.)</th></tr></thead>
            <tbody><?php foreach ($items as $it): ?><tr><td class="whitespace-normal"><?= esc($it['description']) ?></td><td class="text-right"><?= (float) $it['quantity'] ?></td><td class="text-right"><?= money($it['unit_price']) ?></td><td class="text-right text-slate-500"><?= money($it['line_vat']) ?></td><td class="text-right font-semibold"><?= money($it['line_subtotal'] - $it['line_discount']) ?></td></tr><?php endforeach; ?></tbody>
            <tfoot><tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">VAT</td><td class="px-4 py-2 text-right"><?= money($cn['vat_total']) ?></td></tr><tr><td colspan="4" class="px-4 py-2 text-right font-bold">Total credit</td><td class="px-4 py-2 text-right text-lg font-bold">-<?= money($cn['total']) ?></td></tr></tfoot>
        </table></div>
    </div>
    <div class="space-y-6">
        <div class="card"><dl class="card-body space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd class="font-semibold"><?= fdate($cn['issue_date']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Stock returned</dt><dd class="font-semibold"><?= $cn['restock'] ? 'Yes' : 'No' ?></dd></div>
            <div><dt class="text-slate-500">Reason</dt><dd class="mt-1"><?= esc($cn['reason']) ?></dd></div>
            <div class="flex justify-between border-t border-slate-100 pt-3 dark:border-white/10"><dt class="text-slate-500">Invoice balance now</dt><dd class="font-bold"><?= money($invoice['balance_due']) ?></dd></div>
        </dl></div>
        <?php if ($cn['status'] === 'issued'): ?><form method="post" action="<?= site_url('credit-notes/' . $cn['id'] . '/void') ?>" data-confirm="Void this credit note? The invoice balance, VAT and stock will be restored." class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600">Void credit note</button></form><?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
