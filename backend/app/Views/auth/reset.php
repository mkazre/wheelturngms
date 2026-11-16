<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Choose a new password<?= $this->endSection() ?>
<?= $this->section('content') ?>
<h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Choose a new password</h1>
<form method="post" action="<?= site_url('reset-password/' . esc($token, 'url')) ?>" class="mt-8 space-y-5">
    <?= csrf_field() ?>
    <div><label class="label">New password</label><input name="password" type="password" minlength="8" required class="input py-2.5"></div>
    <div><label class="label">Confirm password</label><input name="password_confirm" type="password" minlength="8" required class="input py-2.5"></div>
    <button class="btn-primary w-full py-3">Update password</button>
</form>
<?= $this->endSection() ?>
