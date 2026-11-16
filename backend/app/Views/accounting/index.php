<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Accounting<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Accounting', 'subtitle' => 'Double-entry general ledger. Every invoice, payment, bill and expense posts automatically.',
    'actions' => '<a href="' . site_url('accounting/journals/new') . '" class="btn-light">' . icon('plus', 'h-4 w-4') . ' Journal</a><a href="' . site_url('reports/profit-loss') . '" class="btn-primary">' . icon('book', 'h-4 w-4') . ' Financial statements</a>']) ?>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Net profit (financial YTD)', 'value' => money_short($pl['net_profit']), 'icon' => 'chart', 'tone' => $pl['net_profit'] >= 0 ? 'green' : 'crimson', 'hint' => 'since ' . fdate($fyStart)]) ?>
    <?= view('partials/kpi', ['label' => 'Debtors (owed to us)', 'value' => money_short($ar), 'icon' => 'users', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Creditors (we owe)', 'value' => money_short($ap), 'icon' => 'truck', 'tone' => 'amber']) ?>
    <?= view('partials/kpi', ['label' => 'VAT owing (unfiled + filed)', 'value' => money_short($vatOut - $vatIn + $vatControl), 'icon' => 'shield', 'tone' => 'violet', 'hint' => 'Output ' . money_short($vatOut) . ' · Input ' . money_short($vatIn)]) ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-header"><div class="card-title">Bank &amp; cash accounts</div><span class="text-xs text-slate-500">Import statements to reconcile</span></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                <?php foreach ($banks as $b): ?>
                    <li><a href="<?= site_url('banking/' . $b['id']) ?>" class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-white/5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-50 text-navy-700 dark:bg-white/10 dark:text-white"><?= icon($b['sub_type'] === 'cash' ? 'wallet' : 'building', 'h-5 w-5') ?></span>
                        <div class="min-w-0 flex-1"><div class="font-semibold"><?= esc($b['name']) ?></div><div class="text-xs text-slate-500"><?= esc($b['code']) ?><?= $b['bank_account_number'] ? ' · ' . esc($b['bank_account_number']) : '' ?></div></div>
                        <?php if ($b['unreconciled']): ?><span class="badge-amber"><?= $b['unreconciled'] ?> to reconcile</span><?php endif; ?>
                        <div class="w-32 text-right font-bold <?= $b['balance'] < 0 ? 'text-red-600' : '' ?>"><?= money($b['balance']) ?></div>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Recent journals</div><a href="<?= site_url('accounting/journals') ?>" class="btn-ghost btn-sm">All journals →</a></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                <?php foreach ($journals as $j): ?>
                    <li><a href="<?= site_url('accounting/journals/' . $j['id']) ?>" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-white/5">
                        <span class="w-24 font-mono text-xs text-slate-500"><?= esc($j['number']) ?></span><span class="w-24 text-slate-500"><?= fdate($j['entry_date']) ?></span>
                        <span class="min-w-0 flex-1 truncate"><?= esc($j['description']) ?></span><span class="badge-gray"><?= esc(str_replace('_', ' ', $j['source_type'])) ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Accounting tools</div></div>
            <div class="grid grid-cols-2 gap-2 p-4">
                <?php foreach ([['accounting/accounts', 'Chart of accounts', 'book'], ['accounting/journals', 'Journals', 'clipboard'], ['accounting/vat', 'VAT returns', 'shield'], ['bills', 'Supplier bills', 'receipt'], ['purchase-orders', 'Purchase orders', 'truck'], ['credit-notes', 'Credit notes', 'invoice'], ['reports/trial-balance', 'Trial balance', 'chart'], ['reports/balance-sheet', 'Balance sheet', 'building'], ['reports/cash-flow', 'Cash flow', 'wallet'], ['reports/aged-creditors', 'Aged creditors', 'clock'], ['exports', 'Xero / Sage export', 'download'], ['deposits', 'Customer deposits', 'wallet']] as [$u, $l, $ic]): ?>
                    <a href="<?= site_url($u) ?>" class="flex flex-col items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm font-semibold transition hover:border-crimson-300 hover:bg-crimson-50/40 dark:border-white/10 dark:hover:bg-white/5"><span class="text-crimson-700 dark:text-crimson-400"><?= icon($ic, 'h-5 w-5') ?></span><?= $l ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <form method="post" action="<?= site_url('accounting/lock') ?>" class="card"><?= csrf_field() ?>
            <div class="card-header"><div class="card-title">Period lock</div><?= $lockDate ? '<span class="badge-red">Locked to ' . fdate($lockDate) . '</span>' : '<span class="badge-green">Open</span>' ?></div>
            <div class="card-body space-y-3">
                <p class="text-xs text-slate-500">After VAT is filed or the year-end is signed off, lock the books so nobody can change transactions on or before this date.</p>
                <input type="date" name="lock_date" value="<?= esc($lockDate) ?>" class="input">
                <button class="btn-dark w-full">Save lock date</button>
            </div>
        </form>
        <div class="card"><div class="card-header"><div class="card-title">Suppliers to pay</div><a href="<?= site_url('bills?status=outstanding') ?>" class="btn-ghost btn-sm">Bills →</a></div>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-white/10">
                <?php foreach ($aged as $a): ?><li class="flex justify-between px-5 py-2.5"><span><?= esc($a['name']) ?></span><span class="font-semibold <?= $a['total'] - $a['current'] > 0 ? 'text-red-600' : '' ?>"><?= money($a['total']) ?></span></li><?php endforeach; ?>
                <?php if (!$aged): ?><li class="px-5 py-6 text-center text-slate-500">Nothing owing to suppliers.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
