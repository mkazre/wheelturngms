<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Team<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Team &amp; roles', 'subtitle' => 'Staff accounts for the web app and the Wheelsturn mobile app.', 'actions' => '<a href="' . site_url('users/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Add team member</a>']) ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($rows as $u): $name = trim($u['first_name'] . ' ' . $u['last_name']); ?>
        <a href="<?= site_url('users/' . $u['id'] . '/edit') ?>" class="card flex items-center gap-4 p-5 transition hover:shadow-lg <?= $u['is_active'] ? '' : 'opacity-60' ?>">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br <?= $u['role'] === 'mechanic' ? 'from-navy-700 to-navy-500' : 'from-crimson-700 to-brand-red' ?> text-sm font-bold text-white"><?= esc(initials($name)) ?></div>
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold text-slate-900 dark:text-white"><?= esc($name) ?></div>
                <div class="truncate text-xs text-slate-500"><?= esc($u['job_title'] ?: $labels[$u['role']] ?? $u['role']) ?> · <?= esc($u['branch_name'] ?? '') ?></div>
                <div class="mt-1.5 flex flex-wrap gap-1"><span class="badge-navy"><?= esc($labels[$u['role']] ?? $u['role']) ?></span><?= $u['is_active'] ? '' : '<span class="badge-gray">Inactive</span>' ?></div>
            </div>
            <div class="text-right text-[11px] text-slate-400"><?= $u['last_login_at'] ? 'Active ' . time_ago($u['last_login_at']) : 'Never signed in' ?></div>
        </a>
    <?php endforeach; ?>
</div>
<div class="card mt-8"><div class="card-header"><div class="card-title">What each role can do</div></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Role</th><th>Access</th></tr></thead><tbody>
        <?php foreach (config('Permissions')->roles as $r => $mods): if ($r === 'customer') continue; ?>
            <tr><td class="font-semibold"><?= esc($labels[$r]) ?></td><td class="whitespace-normal"><?php foreach ($mods as $m): ?><span class="badge-gray mr-1 mb-1"><?= $m === '*' ? 'Everything' : esc(ucwords(str_replace('_', ' ', $m))) ?></span><?php endforeach; ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
<?= $this->endSection() ?>
