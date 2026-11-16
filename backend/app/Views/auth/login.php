<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Sign in<?= $this->endSection() ?>
<?= $this->section('content') ?>
<h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Welcome back</h1>
<p class="mt-1 text-sm text-slate-500">Sign in to the Wheelsturn workshop system.</p>
<form method="post" action="<?= site_url('login') ?>" class="mt-8 space-y-5" x-data="{ show: false, busy: false }" @submit="busy = true">
    <?= csrf_field() ?>
    <div>
        <label class="label" for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" required autofocus value="<?= esc(old('email')) ?>" class="input py-2.5">
    </div>
    <div>
        <div class="flex items-center justify-between">
            <label class="label" for="password">Password</label>
            <a href="<?= site_url('forgot-password') ?>" class="mb-1.5 text-xs font-semibold text-crimson-700 hover:underline dark:text-crimson-400">Forgot password?</a>
        </div>
        <div class="relative">
            <input id="password" name="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required class="input py-2.5 pr-16">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-3 text-xs font-semibold text-slate-500" x-text="show ? 'Hide' : 'Show'"></button>
        </div>
    </div>
    <button type="submit" class="btn-primary w-full py-3" :disabled="busy"><span x-show="!busy">Sign in</span><span x-show="busy" x-cloak>Signing in…</span></button>
</form>
<p class="mt-8 text-center text-xs text-slate-400">Protected under POPIA. Unauthorised access is prohibited.</p>
<?= $this->endSection() ?>
