<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Two-factor sign in<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-crimson-50 text-crimson-700"><?= icon('shield', 'h-6 w-6') ?></div>
<h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Enter your security code</h1>
<p class="mt-1 text-sm text-slate-500">Open your authenticator app and enter the 6-digit code for <?= esc(setting('company_name')) ?>.</p>
<form method="post" action="<?= site_url('login/2fa') ?>" class="mt-8 space-y-5"><?= csrf_field() ?>
    <input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="input py-3 text-center font-mono text-2xl tracking-[0.5em]" placeholder="000000">
    <button class="btn-primary w-full py-3">Verify &amp; sign in</button>
    <a href="<?= site_url('login') ?>" class="block text-center text-sm font-semibold text-slate-500">← Back</a>
</form>
<?= $this->endSection() ?>
