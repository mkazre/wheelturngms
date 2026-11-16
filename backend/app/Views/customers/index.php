<?php $g = static fn ($k) => service('request')->getGet($k); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Customers<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => 'Customers', 'subtitle' => number_format($stats['total']) . ' customers · ' . $stats['business'] . ' business accounts · ' . $stats['month'] . ' new this month',
    'actions' => view('partials/export_buttons', ['base' => 'customers']) . '<a href="' . site_url('customers/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New customer</a>',
]) ?>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input name="q" value="<?= esc($g('q')) ?>" placeholder="Search name, phone, email, VAT no. or registration…" class="input flex-1">
        <select name="type" class="input sm:w-44"><option value="">All types</option><option value="individual" <?= $g('type') === 'individual' ? 'selected' : '' ?>>Individuals</option><option value="business" <?= $g('type') === 'business' ? 'selected' : '' ?>>Businesses / fleets</option></select>
        <select name="sort" class="input sm:w-44"><option value="">Newest first</option><option value="balance" <?= $g('sort') === 'balance' ? 'selected' : '' ?>>Highest balance</option><option value="lifetime" <?= $g('sort') === 'lifetime' ? 'selected' : '' ?>>Top spenders</option></select>
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Contact</th><th class="text-center">Vehicles</th><th class="text-right">Lifetime</th><th class="text-right">Balance</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('customers/' . $r['id']) ?>'">
                <td><div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full <?= $r['type'] === 'business' ? 'bg-navy-100 text-navy-700 dark:bg-navy-500/20 dark:text-navy-200' : 'bg-crimson-50 text-crimson-700 dark:bg-crimson-500/15 dark:text-crimson-300' ?> text-xs font-bold"><?= esc(initials(customer_name($r))) ?></div>
                    <div><div class="font-semibold text-slate-900 dark:text-white"><?= esc(customer_name($r)) ?></div><div class="text-xs text-slate-500"><?= esc($r['code']) ?> · <?= $r['type'] === 'business' ? 'Business' : 'Individual' ?></div></div>
                </div></td>
                <td><div><?= esc($r['phone'] ?: '—') ?></div><div class="text-xs text-slate-500"><?= esc($r['email']) ?></div></td>
                <td class="text-center"><span class="badge-gray"><?= $r['vehicles_count'] ?></span></td>
                <td class="text-right"><?= money($r['lifetime']) ?></td>
                <td class="text-right font-semibold <?= $r['balance'] > 0 ? 'text-red-600' : 'text-slate-400' ?>"><?= money($r['balance']) ?></td>
                <td class="text-right text-slate-400"><?= icon('arrow-right', 'h-4 w-4') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?>
        <?= view('partials/empty', ['icon' => 'users', 'title' => 'No customers found', 'text' => 'Add your first customer to start creating job cards and invoices.', 'action' => '<a href="' . site_url('customers/new') . '" class="btn-primary">Add customer</a>']) ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
