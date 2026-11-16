<?php $stmtBal = $lastStatement['running_balance'] ?? null; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($acc['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => esc($acc['name']), 'subtitle' => 'Bank reconciliation · ' . esc($acc['code']) . ($acc['bank_account_number'] ? ' · ' . esc($acc['bank_account_number']) : ''), 'crumbs' => ['accounting' => 'Accounting'],
    'actions' => '<a href="' . site_url('accounting/ledger/' . $acc['id']) . '" class="btn-light">' . icon('book', 'h-4 w-4') . ' Ledger</a><form method="post" action="' . site_url('banking/' . $acc['id'] . '/auto-match') . '">' . csrf_field() . '<button class="btn-dark">' . icon('zap', 'h-4 w-4') . ' Auto-match</button></form>']) ?>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Balance per books', 'value' => money($ledgerBalance), 'icon' => 'book', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Balance per statement', 'value' => $stmtBal !== null ? money($stmtBal) : '—', 'icon' => 'building', 'tone' => 'sky', 'hint' => $lastStatement ? 'as at ' . fdate($lastStatement['txn_date']) : 'import a statement']) ?>
    <?= view('partials/kpi', ['label' => 'Difference', 'value' => $stmtBal !== null ? money($ledgerBalance - (float) $stmtBal) : '—', 'icon' => 'alert', 'tone' => $stmtBal !== null && abs($ledgerBalance - (float) $stmtBal) < 0.01 ? 'green' : 'amber', 'hint' => 'unreconciled in books ' . money($unreconciledLedger)]) ?>
    <?= view('partials/kpi', ['label' => 'Lines to reconcile', 'value' => (int) ($counts['unmatched'] ?? 0), 'icon' => 'clipboard', 'tone' => ($counts['unmatched'] ?? 0) ? 'crimson' : 'green', 'hint' => (int) ($counts['matched'] ?? 0) . ' matched']) ?>
</div>

<form method="post" enctype="multipart/form-data" action="<?= site_url('banking/' . $acc['id'] . '/upload') ?>" class="card mb-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-center"><?= csrf_field() ?>
    <div class="flex-1"><div class="font-semibold">Import bank statement</div><p class="text-xs text-slate-500">Download a CSV from FNB, ABSA, Standard Bank, Nedbank, Capitec or Investec online banking. Duplicates are skipped automatically.</p></div>
    <input type="file" name="statement" accept=".csv,.txt" required class="text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white">
    <button class="btn-primary"><?= icon('upload', 'h-4 w-4') ?> Upload</button>
</form>

<div class="mb-4 flex gap-2">
    <?php foreach (['unmatched' => 'To reconcile', 'matched' => 'Reconciled', 'ignored' => 'Ignored', 'all' => 'All'] as $k => $l): ?><a href="?status=<?= $k ?>" class="rounded-full px-3.5 py-1.5 text-xs font-semibold <?= $status === $k ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' ?>"><?= $l ?> <?= isset($counts[$k]) ? '<span class="opacity-60">' . $counts[$k] . '</span>' : '' ?></a><?php endforeach; ?>
</div>

<div class="space-y-3">
<?php foreach ($rows as $t): $in = (float) $t['amount'] > 0; ?>
    <div class="card p-4" x-data="{ mode: null }">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="w-28 shrink-0 text-sm font-semibold"><?= fdate($t['txn_date']) ?></div>
            <div class="min-w-0 flex-1"><div class="truncate font-medium"><?= esc($t['description']) ?></div><div class="text-xs text-slate-500"><?= esc($t['reference']) ?><?= $t['journal_number'] ? ' · matched to ' . esc($t['journal_number']) : '' ?></div></div>
            <div class="w-36 text-right text-lg font-bold <?= $in ? 'text-emerald-600' : 'text-slate-900 dark:text-white' ?>"><?= $in ? '+' : '−' ?><?= money(abs((float) $t['amount'])) ?></div>
            <div class="flex shrink-0 flex-wrap gap-1.5">
                <?php if ($t['status'] === 'unmatched'): ?>
                    <?php if ($t['candidates']): ?><button type="button" @click="mode = mode === 'match' ? null : 'match'" class="btn-primary btn-sm">Match (<?= count($t['candidates']) ?>)</button><?php endif; ?>
                    <button type="button" @click="mode = mode === 'create' ? null : 'create'" class="btn-light btn-sm">Create</button>
                    <form method="post" action="<?= site_url('banking/txn/' . $t['id'] . '/ignore') ?>"><?= csrf_field() ?><button class="btn-ghost btn-sm">Ignore</button></form>
                <?php else: ?>
                    <span class="<?= $t['status'] === 'matched' ? 'badge-green' : 'badge-gray' ?>"><?= ucfirst($t['status']) ?></span>
                    <form method="post" action="<?= site_url('banking/txn/' . $t['id'] . '/unmatch') ?>"><?= csrf_field() ?><button class="btn-ghost btn-sm">Undo</button></form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($t['candidates']): ?>
        <div x-show="mode === 'match'" x-cloak class="mt-3 space-y-1.5 rounded-xl bg-slate-50 p-3 dark:bg-white/5">
            <div class="label">Matching entries in the books</div>
            <?php foreach ($t['candidates'] as $c): ?>
                <form method="post" action="<?= site_url('banking/txn/' . $t['id'] . '/match') ?>" class="flex items-center gap-3 text-sm"><?= csrf_field() ?><input type="hidden" name="line_id" value="<?= $c['line_id'] ?>">
                    <span class="w-24"><?= fdate($c['entry_date']) ?></span><span class="font-mono text-xs"><?= esc($c['number']) ?></span><span class="flex-1 truncate"><?= esc($c['description']) ?></span>
                    <span class="font-semibold"><?= money((float) $c['debit'] ?: (float) $c['credit']) ?></span><button class="btn-dark btn-sm">Match</button>
                </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form x-show="mode === 'create'" x-cloak method="post" action="<?= site_url('banking/txn/' . $t['id'] . '/create') ?>" class="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-5 sm:items-end dark:bg-white/5"><?= csrf_field() ?>
            <div class="sm:col-span-2"><label class="label"><?= $in ? 'Money in from (credit account)' : 'Spent on (debit account)' ?></label>
                <select name="account_id" required class="input py-1.5 text-sm"><option value="">Select…</option><?php foreach ($contraAccounts as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?></select></div>
            <div class="sm:col-span-2"><label class="label">Description</label><input name="description" value="<?= esc($t['description']) ?>" class="input py-1.5 text-sm"></div>
            <div class="flex items-center justify-between gap-2"><label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="includes_vat" value="1" class="rounded text-crimson-600"> incl. VAT</label><button class="btn-primary btn-sm">Post</button></div>
        </form>
    </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><?= view('partials/empty', ['icon' => 'check', 'title' => $status === 'unmatched' ? 'All reconciled' : 'Nothing here', 'text' => 'Import a statement CSV to start reconciling.']) ?></div><?php endif; ?>
</div>
<div class="mt-4"><?= view('partials/pagination', ['pager' => $pager]) ?></div>
<?= $this->endSection() ?>
