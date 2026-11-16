<?php $cur = service('request')->getGet('status'); $sum = static fn ($k) => (float) ($stats[$k]['amt'] ?? 0); $cnt = static fn ($k) => (int) ($stats[$k]['n'] ?? 0); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Quotes<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Quotes &amp; estimates', 'subtitle' => 'Customers can accept or decline online with one click.',
    'actions' => view('partials/export_buttons', ['base' => 'quotes']) . '<a href="' . site_url('quotes/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New quote</a>']) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Awaiting response', 'value' => money_short($sum('sent') + $sum('draft')), 'icon' => 'clock', 'tone' => 'amber', 'hint' => ($cnt('sent') + $cnt('draft')) . ' quotes']) ?>
    <?= view('partials/kpi', ['label' => 'Accepted', 'value' => money_short($sum('accepted') + $sum('converted')), 'icon' => 'check', 'tone' => 'green', 'hint' => ($cnt('accepted') + $cnt('converted')) . ' quotes']) ?>
    <?= view('partials/kpi', ['label' => 'Declined', 'value' => money_short($sum('declined')), 'icon' => 'x', 'tone' => 'crimson', 'hint' => $cnt('declined') . ' quotes']) ?>
    <?php $all = array_sum(array_column($stats, 'n')); $won = $cnt('accepted') + $cnt('converted'); ?>
    <?= view('partials/kpi', ['label' => 'Win rate', 'value' => ($all ? round($won / $all * 100) : 0) . '%', 'icon' => 'zap', 'tone' => 'violet', 'hint' => $all . ' total']) ?>
</div>
<div class="card">
    <form class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row dark:border-white/10">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search quote no., customer or registration…" class="input flex-1">
        <select name="status" class="input sm:w-44"><option value="">All statuses</option><?php foreach (['draft', 'sent', 'accepted', 'declined', 'expired', 'converted'] as $s): ?><option value="<?= $s ?>" <?= $cur === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
        <button class="btn-dark">Filter</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Quote</th><th>Customer</th><th>Vehicle</th><th>Status</th><th>Expires</th><th class="text-right">Total</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('quotes/' . $r['id']) ?>'">
                <td><div class="font-semibold text-slate-900 dark:text-white"><?= esc($r['number']) ?></div><div class="text-xs text-slate-500"><?= fdate($r['issue_date']) ?></div></td>
                <td><?= esc(customer_name($r)) ?></td>
                <td><?= $r['registration'] ? esc($r['make'] . ' ' . $r['model']) . ' <span class="font-mono text-xs text-slate-500">' . esc($r['registration']) . '</span>' : '—' ?></td>
                <td><?= status_badge($r['status'], 'quote') ?></td>
                <td class="text-slate-500"><?= fdate($r['expiry_date']) ?></td>
                <td class="text-right font-semibold"><?= money($r['total']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'quote', 'title' => 'No quotes yet', 'action' => '<a href="' . site_url('quotes/new') . '" class="btn-primary">Create a quote</a>']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
