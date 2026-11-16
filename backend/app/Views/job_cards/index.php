<?php $cur = service('request')->getGet('status'); $isMech = session('role') === 'mechanic'; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Job Cards<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => $isMech ? 'My jobs' : 'Job cards', 'subtitle' => 'Every vehicle through the workshop, from check-in to collection.',
    'actions' => ($isMech ? '' : view('partials/export_buttons', ['base' => 'job-cards'])) . '<a href="' . site_url('workshop') . '" class="btn-light">' . icon('board', 'h-4 w-4') . ' Board view</a>' . ($isMech ? '' : '<a href="' . site_url('job-cards/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New job card</a>'),
]) ?>

<div class="mb-4 flex gap-2 overflow-x-auto pb-1">
    <?php $pill = static fn ($k, $label, $n = null) => '<a href="?status=' . $k . '" class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition ' . ($cur === $k || ($k === 'open' && $cur === null) ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10') . '">' . $label . ($n !== null ? ' <span class="opacity-60">' . $n . '</span>' : '') . '</a>'; ?>
    <?= $pill('open', 'Open') ?>
    <?php foreach (job_statuses() as $k => $s): ?><?= $pill($k, $s['label'], (int) ($counts[$k] ?? 0)) ?><?php endforeach; ?>
    <?= $pill('all', 'All') ?>
</div>

<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10">
        <input type="hidden" name="status" value="<?= esc($cur) ?>">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search job no., registration, customer or complaint…" class="input flex-1">
        <button class="btn-dark">Search</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Job</th><th>Vehicle</th><th>Customer</th><th>Status</th><th>Technicians</th><th>Promised</th><?php if (!$isMech): ?><th class="text-right">Total</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $late = $r['promised_at'] && strtotime($r['promised_at']) < time() && !in_array($r['status'], ['completed', 'invoiced', 'collected', 'cancelled'], true); ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('job-cards/' . $r['id']) ?>'">
                <td><div class="flex items-center gap-2"><span class="font-semibold text-slate-900 dark:text-white"><?= esc($r['number']) ?></span><?php if ($r['priority'] === 'urgent'): ?><span class="badge-red">Urgent</span><?php endif; ?></div><div class="text-xs text-slate-500"><?= esc($r['job_type'] ?: '—') ?> · <?= fdate($r['created_at']) ?></div></td>
                <td><div class="font-medium"><?= esc(trim($r['make'] . ' ' . $r['model'])) ?></div><span class="font-mono text-xs text-slate-500"><?= esc($r['registration']) ?></span></td>
                <td><?= esc(customer_name($r)) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="text-slate-500"><?= esc($r['mechanic_names'] ?: '—') ?></td>
                <td class="<?= $late ? 'font-semibold text-red-600' : 'text-slate-500' ?>"><?= $r['promised_at'] ? fdatetime($r['promised_at']) : '—' ?></td>
                <?php if (!$isMech): ?><td class="text-right font-semibold"><?= money($r['total']) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?>
        <?= view('partials/empty', ['icon' => 'wrench', 'title' => 'No job cards here', 'text' => 'Open a job card when a vehicle arrives.', 'action' => $isMech ? '' : '<a href="' . site_url('job-cards/new') . '" class="btn-primary">New job card</a>']) ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
