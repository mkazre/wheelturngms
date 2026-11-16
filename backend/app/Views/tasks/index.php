<?php $done = (bool) service('request')->getGet('done'); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Tasks<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Tasks &amp; reminders', 'subtitle' => 'Call-backs, parts to chase, follow-ups. Assign to anyone on the team.']) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2">
        <div class="mb-4 flex gap-2">
            <?php foreach (['?scope=mine' => 'My tasks', '?scope=all' => 'Everyone', '?scope=' . $scope . '&done=1' => 'Completed'] as $u => $l): ?><a href="<?= $u ?>" class="rounded-full px-3.5 py-1.5 text-xs font-semibold <?= ($l === 'Completed' && $done) || (!$done && (($l === 'My tasks' && $scope === 'mine') || ($l === 'Everyone' && $scope === 'all'))) ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10' ?>"><?= $l ?></a><?php endforeach; ?>
        </div>
        <div class="card"><ul class="divide-y divide-slate-100 dark:divide-white/10">
            <?php foreach ($rows as $t): $late = !$done && $t['due_at'] && strtotime($t['due_at']) < time();
                $link = match ($t['entity_type']) { 'job_card' => 'job-cards/', 'customer' => 'customers/', 'invoice' => 'invoices/', 'vehicle' => 'vehicles/', default => null }; ?>
                <li class="flex items-start gap-3 px-5 py-4">
                    <form method="post" action="<?= site_url('tasks/' . $t['id'] . '/complete') ?>"><?= csrf_field() ?><button class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 <?= $done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 hover:border-crimson-500' ?>"><?= $done ? icon('check', 'h-3 w-3') : '' ?></button></form>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold <?= $done ? 'text-slate-400 line-through' : '' ?>"><?= esc($t['title']) ?> <?= $t['priority'] === 'high' ? '<span class="badge-red">High</span>' : '' ?></div>
                        <?php if ($t['notes']): ?><p class="text-sm text-slate-500"><?= esc($t['notes']) ?></p><?php endif; ?>
                        <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500"><span><?= esc($t['assignee'] ?? '—') ?></span><?php if ($t['due_at']): ?><span class="<?= $late ? 'font-semibold text-red-600' : '' ?>"><?= icon('clock', 'inline h-3 w-3') ?> <?= fdatetime($t['due_at']) ?></span><?php endif; ?><?php if ($link && $t['entity_id']): ?><a href="<?= site_url($link . $t['entity_id']) ?>" class="text-crimson-700">Open record →</a><?php endif; ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$rows): ?><li><?= view('partials/empty', ['icon' => 'check', 'title' => $done ? 'Nothing completed yet' : 'All clear', 'text' => $done ? '' : 'No open tasks.']) ?></li><?php endif; ?>
        </ul></div>
    </div>
    <form method="post" action="<?= site_url('tasks/save') ?>" class="card h-fit"><?= csrf_field() ?>
        <div class="card-header"><div class="card-title">New task</div></div>
        <div class="card-body space-y-3">
            <input name="title" required placeholder="e.g. Call Mr Botha re: brake quote" class="input">
            <textarea name="notes" rows="2" placeholder="Notes" class="input"></textarea>
            <div class="grid grid-cols-2 gap-2">
                <select name="assigned_to" class="input"><?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>" <?= (int) $s['id'] === (int) session('user_id') ? 'selected' : '' ?>><?= esc($s['first_name'] . ' ' . $s['last_name']) ?></option><?php endforeach; ?></select>
                <select name="priority" class="input"><option value="normal">Normal</option><option value="high">High</option></select>
            </div>
            <input type="datetime-local" name="due_at" class="input">
            <button class="btn-primary w-full">Add task</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
