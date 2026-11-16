<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Forgot password<?= $this->endSection() ?>
<?= $this->section('content') ?>
<h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Reset your password</h1>
<p class="mt-1 text-sm text-slate-500">Enter your email and we'll send you a secure reset link.</p>
<form method="post" action="<?= site_url('forgot-password') ?>" class="mt-8 space-y-5">
    <?= csrf_field() ?>
    <div><label class="label">Email address</label><input name="email" type="email" required autofocus class="input py-2.5"></div>
    <button class="btn-primary w-full py-3">Send reset link</button>
    <a href="<?= site_url('login') ?>" class="block text-center text-sm font-semibold text-slate-500 hover:text-slate-800">← Back to sign in</a>
</form>
<?= $this->endSection() ?>
