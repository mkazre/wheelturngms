<?= $this->extend(session('role') === 'customer' ? 'layouts/public' : 'layouts/app') ?>
<?= $this->section('title') ?>My profile<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'My profile', 'subtitle' => esc($u['email'])]) ?>
<form method="post" action="<?= site_url('profile') ?>" class="grid max-w-4xl gap-6 lg:grid-cols-2"><?= csrf_field() ?>
    <div class="card"><div class="card-header"><div class="card-title">Details</div></div><div class="card-body space-y-4">
        <div><label class="label">First name</label><input name="first_name" value="<?= esc($u['first_name']) ?>" class="input" required></div>
        <div><label class="label">Last name</label><input name="last_name" value="<?= esc($u['last_name']) ?>" class="input"></div>
        <div><label class="label">Mobile</label><input name="phone" value="<?= esc($u['phone']) ?>" class="input"></div>
    </div></div>
    <div class="card"><div class="card-header"><div class="card-title">Change password</div></div><div class="card-body space-y-4">
        <div><label class="label">Current password</label><input name="current_password" type="password" class="input" autocomplete="current-password"></div>
        <div><label class="label">New password</label><input name="new_password" type="password" minlength="8" class="input" autocomplete="new-password"></div>
        <div><label class="label">Confirm new password</label><input name="confirm_password" type="password" minlength="8" class="input" autocomplete="new-password"></div>
    </div></div>
    <div><button class="btn-primary"><?= icon('check', 'h-4 w-4') ?> Save profile</button></div>
</form>

<?php $setup = session('totp_setup'); ?>
<div id="security" class="card mt-6 max-w-4xl">
    <div class="card-header"><div class="card-title flex items-center gap-2"><?= icon('shield', 'h-4 w-4 text-crimson-600') ?> Two-factor authentication</div><?= (int) $u['totp_enabled'] ? '<span class="badge-green">On</span>' : '<span class="badge-gray">Off</span>' ?></div>
    <div class="card-body text-sm">
        <?php if ((int) $u['totp_enabled']): ?>
            <p class="text-slate-600 dark:text-slate-300">Your account is protected with an authenticator app.</p>
            <form method="post" action="<?= site_url('profile/2fa') ?>" class="mt-3 flex max-w-md gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><input type="password" name="password" placeholder="Your password" required class="input"><button class="btn-light">Turn off</button></form>
        <?php elseif ($setup): ?>
            <div class="grid gap-6 sm:grid-cols-[auto,1fr] sm:items-center">
                <img src="<?= \App\Libraries\Notify::qr(\App\Libraries\Totp::uri($setup, $u['email']), 5) ?>" alt="QR code" class="h-44 w-44 rounded-xl bg-white p-2">
                <div class="space-y-3">
                    <p>1. Scan this code with Google Authenticator, Microsoft Authenticator or Authy.<br>Can't scan? Enter this key: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs dark:bg-white/10"><?= esc(chunk_split($setup, 4, ' ')) ?></code></p>
                    <form method="post" action="<?= site_url('profile/2fa') ?>" class="flex max-w-xs gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="confirm">
                        <input name="code" inputmode="numeric" maxlength="7" placeholder="6-digit code" required class="input font-mono"><button class="btn-primary">Turn on</button></form>
                    <p class="text-xs text-slate-500">2. Enter the code the app shows to finish.</p>
                </div>
            </div>
        <?php else: ?>
            <p class="text-slate-600 dark:text-slate-300">Add a second step at sign-in using your phone, strongly recommended for administrators and anyone with access to finances.</p>
            <form method="post" action="<?= site_url('profile/2fa') ?>" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="start"><button class="btn-dark">Set up two-factor</button></form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
