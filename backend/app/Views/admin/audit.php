<?php $g = static fn ($k) => service('request')->getGet($k); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Audit log<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Audit log', 'subtitle' => 'Who did what, and when, across the whole system. Kept for compliance and dispute resolution.']) ?>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row dark:border-white/10">
        <input name="q" value="<?= esc($g('q')) ?>" placeholder="Search descriptions…" class="input flex-1">
        <select name="user" class="input lg:w-48"><option value="">All users</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (int) $g('user') === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></option><?php endforeach; ?></select>
        <select name="type" class="input lg:w-44"><option value="">All records</option><?php foreach ($types as $t): ?><option value="<?= esc($t) ?>" <?= $g('type') === $t ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= esc($from) ?>" class="input lg:w-40"><input type="date" name="to" value="<?= esc($to) ?>" class="input lg:w-40">
        <button class="btn-dark">Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
        <tbody><?php foreach ($rows as $r): $link = match ($r['entity_type']) { 'job_card' => 'job-cards/', 'invoice' => 'invoices/', 'quote' => 'quotes/', 'customer' => 'customers/', 'vehicle' => 'vehicles/', 'bill' => 'bills/', 'journal' => 'accounting/journals/', default => null }; ?>
            <tr><td class="text-slate-500"><?= fdatetime($r['created_at']) ?></td><td class="font-medium"><?= esc(trim(($r['first_name'] ?? 'System') . ' ' . ($r['last_name'] ?? ''))) ?></td>
                <td><span class="badge-gray"><?= esc(str_replace('_', ' ', $r['action'])) ?></span></td>
                <td><?= $link && $r['entity_id'] ? '<a class="text-crimson-700" href="' . site_url($link . $r['entity_id']) . '">' . esc(str_replace('_', ' ', $r['entity_type'])) . ' #' . $r['entity_id'] . '</a>' : esc((string) $r['entity_type']) ?></td>
                <td class="max-w-md truncate"><?= esc($r['description']) ?></td><td class="font-mono text-xs text-slate-400"><?= esc($r['ip_address']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
</div>
<?= $this->endSection() ?>
