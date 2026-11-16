<?php $spark = str_replace('\\', '/', ROOTPATH) . 'spark'; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>System<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'System health, scheduled tasks and email delivery log.']) ?>
<div class="grid gap-6 lg:grid-cols-4">
    <?= view('settings/_nav', ['sections' => $sections, 'section' => $section]) ?>
    <div class="space-y-6 lg:col-span-3">
        <div class="card"><div class="card-header"><div class="card-title">Scheduled tasks (cPanel cron)</div><?= setting('cron_daily_last') ? '<span class="badge-green">Last run ' . esc(setting('cron_daily_last')) . '</span>' : '<span class="badge-amber">Not detected yet</span>' ?></div>
            <div class="card-body space-y-3 text-sm">
                <p>Add <b>one</b> cron job in cPanel → <i>Cron Jobs</i>, running every 15 minutes. It sends the Monday 07:00 report, overdue payment reminders, and expires old quotes.</p>
                <code class="block overflow-x-auto whitespace-nowrap rounded-xl bg-navy-950 px-4 py-3 font-mono text-xs text-emerald-300">*/15 * * * * /usr/local/bin/php <?= esc($spark) ?> gms:cron &gt;/dev/null 2&gt;&amp;1</code>
                <p class="text-xs text-slate-500">Manual commands: <code>php spark reports:weekly</code> · <code>php spark gms:cron</code></p>
            </div>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Environment</div></div>
            <dl class="card-body grid gap-3 text-sm sm:grid-cols-2">
                <?php foreach (['PHP' => PHP_VERSION, 'CodeIgniter' => \CodeIgniter\CodeIgniter::CI_VERSION, 'Environment' => ENVIRONMENT, 'Database' => db_connect()->DBDriver, 'Timezone' => date_default_timezone_get(), 'Base URL' => base_url(), 'Uploads writable' => is_writable(WRITEPATH . 'uploads') ? 'Yes' : 'NO, fix permissions', 'HTTPS' => service('request')->isSecure() ? 'Yes' : 'No (required for live payments)'] as $k => $v): ?>
                    <div class="flex justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-white/5"><dt class="text-slate-500"><?= $k ?></dt><dd class="font-semibold"><?= esc($v) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Email delivery log</div></div>
            <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($emailLog as $e): ?><tr><td class="text-slate-500"><?= time_ago($e['created_at']) ?></td><td><?= esc($e['recipient']) ?></td><td class="max-w-xs truncate"><?= esc($e['subject']) ?></td><td><span class="<?= $e['status'] === 'sent' ? 'badge-green' : 'badge-red' ?>" title="<?= esc($e['error']) ?>"><?= esc($e['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if (!$emailLog): ?><tr><td colspan="4" class="py-6 text-center text-slate-500">No emails sent yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
