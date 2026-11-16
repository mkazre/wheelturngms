<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Search<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Search results', 'subtitle' => $hits . ' results for “' . esc($q) . '”']) ?>
<?php $groups = [
    'customers' => ['Customers', 'users', static fn ($r) => [site_url('customers/' . $r['id']), customer_name($r), implode(' · ', array_filter([$r['phone'], $r['email']]))]],
    'vehicles' => ['Vehicles', 'car', static fn ($r) => [site_url('vehicles/' . $r['id']), $r['registration'], trim($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])]],
    'jobs' => ['Job cards', 'wrench', static fn ($r) => [site_url('job-cards/' . $r['id']), $r['number'], $r['registration'] . ' · ' . $r['make'] . ' ' . $r['model']]],
    'invoices' => ['Invoices', 'invoice', static fn ($r) => [site_url('invoices/' . $r['id']), $r['number'], fdate($r['issue_date']) . ' · ' . money($r['total'])]],
    'quotes' => ['Quotes', 'quote', static fn ($r) => [site_url('quotes/' . $r['id']), $r['number'], fdate($r['issue_date']) . ' · ' . money($r['total'])]],
]; ?>
<div class="grid gap-6 lg:grid-cols-2">
    <?php foreach ($groups as $k => [$title, $ic, $fn]): if (!$res[$k]) continue; ?>
        <div class="card"><div class="card-header"><div class="card-title flex items-center gap-2"><?= icon($ic, 'h-4 w-4 text-crimson-600') ?> <?= $title ?></div><span class="badge-gray"><?= count($res[$k]) ?></span></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                <?php foreach ($res[$k] as $r): [$url, $main, $sub] = $fn($r); ?>
                    <li><a href="<?= $url ?>" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 dark:hover:bg-white/5"><div><div class="font-semibold"><?= esc($main) ?></div><div class="text-xs text-slate-500"><?= esc($sub) ?></div></div><?= icon('arrow-right', 'h-4 w-4 text-slate-300') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$hits): ?><div class="card"><?= view('partials/empty', ['icon' => 'search', 'title' => 'Nothing found', 'text' => 'Try a registration number, phone number, invoice or job card number.']) ?></div><?php endif; ?>
<?= $this->endSection() ?>
