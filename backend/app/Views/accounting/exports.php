<?php $qs = http_build_query(['from' => $from, 'to' => $to]); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Accounting exports<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Export to Xero &amp; Sage', 'subtitle' => 'Wheelsturn has its own full ledger. These exports let an external accountant pull your books into Xero or Sage.', 'crumbs' => ['accounting' => 'Accounting']]) ?>
<form class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
    <div><label class="label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="input"></div>
    <button class="btn-dark">Set period</button>
</form>
<div class="grid gap-6 md:grid-cols-2">
    <?php foreach ([
        ['Xero', 'Manual journals', 'Every ledger posting as Xero manual journals (Accounting → Manual Journals → Import).', 'exports/xero-journals'],
        ['Xero', 'Sales invoices', 'Invoices line-by-line in Xero\'s sales invoice import format (Business → Invoices → Import).', 'exports/xero-invoices'],
        ['Sage', 'Journals', 'Sage Business Cloud Accounting journal import (Accounting → Journals → Import).', 'exports/sage-journals'],
        ['Both', 'Chart of accounts', 'Set up matching account codes first so imports map one-to-one.', 'exports/chart'],
    ] as [$sys, $title, $desc, $url]): ?>
        <div class="card flex flex-col p-6">
            <span class="<?= $sys === 'Xero' ? 'badge-blue' : ($sys === 'Sage' ? 'badge-green' : 'badge-gray') ?> w-fit"><?= $sys ?></span>
            <div class="mt-3 text-lg font-bold"><?= $title ?></div><p class="mt-1 flex-1 text-sm text-slate-500"><?= $desc ?></p>
            <a href="<?= site_url($url) . '?' . $qs ?>" class="btn-primary mt-4 w-fit"><?= icon('download', 'h-4 w-4') ?> Download CSV</a>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
