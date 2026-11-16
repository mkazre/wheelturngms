<?php $isEdit = !empty($u['id']); $val = static fn ($k, $d = '') => esc(old($k, $u[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit team member' : 'Add team member' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? esc($u['first_name'] . ' ' . $u['last_name']) : 'Add team member', 'crumbs' => ['users' => 'Team']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'users/' . $u['id'] . '/save' : 'users/save') ?>" class="grid gap-6 lg:grid-cols-3"><?= csrf_field() ?>
    <div class="card lg:col-span-2"><div class="card-body grid gap-4 sm:grid-cols-2">
        <div><label class="label">First name *</label><input name="first_name" value="<?= $val('first_name') ?>" required class="input"></div>
        <div><label class="label">Last name</label><input name="last_name" value="<?= $val('last_name') ?>" class="input"></div>
        <div><label class="label">Email (login) *</label><input name="email" type="email" value="<?= $val('email') ?>" required class="input"></div>
        <div><label class="label">Mobile</label><input name="phone" value="<?= $val('phone') ?>" class="input"></div>
        <div><label class="label">Role *</label><select name="role" class="input"><?php foreach ($labels as $k => $l): ?><option value="<?= $k ?>" <?= ($u['role'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div><label class="label">Job title</label><input name="job_title" value="<?= $val('job_title') ?>" class="input" placeholder="Senior technician"></div>
        <div><label class="label">Branch</label><select name="branch_id" class="input"><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (int) ($u['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="label"><?= $isEdit ? 'New password (leave blank to keep)' : 'Password *' ?></label><input name="password" type="password" minlength="8" autocomplete="new-password" class="input" <?= $isEdit ? '' : 'required' ?>></div>
    </div></div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Rates</div></div><div class="card-body space-y-4">
            <div><label class="label">Charge-out rate (R/hour)</label><input name="hourly_rate" type="number" step="0.01" value="<?= $val('hourly_rate', 0) ?>" class="input"></div>
            <div><label class="label">Cost rate (R/hour, for job costing)</label><input name="cost_rate" type="number" step="0.01" value="<?= $val('cost_rate', 0) ?>" class="input"></div>
            <div class="grid grid-cols-2 gap-3"><div><label class="label">Commission % on labour</label><input name="commission_percent" type="number" step="0.1" value="<?= $val('commission_percent', 0) ?>" class="input"></div>
                <div><label class="label">Monthly labour target (R)</label><input name="monthly_target" type="number" step="100" value="<?= $val('monthly_target', 0) ?>" class="input"></div></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= !empty($u['is_active']) ? 'checked' : '' ?> class="rounded text-crimson-600 focus:ring-crimson-500"> Active (can sign in)</label>
        </div></div>
        <button class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Save</button>
        <?php if ($isEdit && (int) $u['id'] !== (int) session('user_id')): ?><button form="delUser" class="btn-ghost btn-sm w-full text-red-600">Remove team member</button><?php endif; ?>
    </div>
</form>
<?php if ($isEdit): ?><form id="delUser" method="post" action="<?= site_url('users/' . $u['id'] . '/delete') ?>" data-confirm="Remove this team member?"><?= csrf_field() ?></form><?php endif; ?>
<?= $this->endSection() ?>
