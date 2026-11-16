<?php [$title, $ic, $desc, $fields] = $sections[$section]; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Settings<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Configure Wheelsturn GMS to work the way your workshop does.']) ?>
<div class="grid gap-6 lg:grid-cols-4">
    <?= view('settings/_nav', ['sections' => $sections, 'section' => $section]) ?>
    <div class="space-y-6 lg:col-span-3">
        <form method="post" action="<?= site_url('settings/' . $section) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><div><div class="card-title flex items-center gap-2"><?= icon($ic, 'h-4 w-4 text-crimson-600') ?> <?= esc($title) ?></div><p class="mt-0.5 text-xs text-slate-500"><?= esc($desc) ?></p></div></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <?php foreach ($fields as $key => $f): [$label, $type] = $f; $help = $f[2] ?? null; $opts = $f[3] ?? []; $val = (string) ($values[$key] ?? ''); $wide = in_array($type, ['textarea'], true); ?>
                    <div class="<?= $wide ? 'sm:col-span-2' : '' ?>">
                        <?php if ($type === 'toggle'): ?>
                            <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-slate-200 p-3.5 dark:border-white/10" x-data="{ on: <?= $val === '1' ? 'true' : 'false' ?> }">
                                <span><span class="block text-sm font-semibold"><?= esc($label) ?></span><?php if ($help): ?><span class="block text-xs text-slate-500"><?= esc($help) ?></span><?php endif; ?></span>
                                <input type="checkbox" name="<?= $key ?>" value="1" class="sr-only" x-model="on">
                                <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full transition" :class="on ? 'bg-crimson-600' : 'bg-slate-300 dark:bg-white/20'"><span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="on ? 'left-[22px]' : 'left-0.5'"></span></span>
                            </label>
                        <?php else: ?>
                            <label class="label" for="<?= $key ?>"><?= esc($label) ?></label>
                            <?php if ($type === 'textarea'): ?>
                                <textarea id="<?= $key ?>" name="<?= $key ?>" rows="<?= $key === 'inspection_template' ? 10 : 3 ?>" class="input <?= $key === 'inspection_template' ? 'font-mono text-xs' : '' ?>"><?= esc($val) ?></textarea>
                            <?php elseif ($type === 'select'): ?>
                                <select id="<?= $key ?>" name="<?= $key ?>" class="input"><?php foreach ($opts as $ov => $ol): ?><option value="<?= esc($ov) ?>" <?= (string) $ov === $val ? 'selected' : '' ?>><?= esc($ol) ?></option><?php endforeach; ?></select>
                            <?php elseif ($type === 'password'): ?>
                                <input id="<?= $key ?>" name="<?= $key ?>" type="password" autocomplete="new-password" placeholder="<?= $val !== '' ? '•••••••• (saved; leave blank to keep)' : 'Not set' ?>" class="input">
                            <?php else: ?>
                                <input id="<?= $key ?>" name="<?= $key ?>" type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" <?= $type === 'number' ? 'step="any"' : '' ?> value="<?= esc($val) ?>" class="input">
                            <?php endif; ?>
                            <?php if ($help): ?><p class="mt-1 text-xs text-slate-500"><?= esc($help) ?></p><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-end border-t border-slate-100 p-5 dark:border-white/10"><button class="btn-primary"><?= icon('check', 'h-4 w-4') ?> Save changes</button></div>
        </form>

        <?php if ($section === 'email'): ?>
            <form method="post" action="<?= site_url('settings/email/test') ?>" class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-end"><?= csrf_field() ?>
                <div class="flex-1"><label class="label">Send a test email to</label><input name="to" type="email" value="<?= esc(auth_user('email')) ?>" class="input"></div>
                <button class="btn-dark"><?= icon('mail', 'h-4 w-4') ?> Send test</button>
            </form>
        <?php endif; ?>

        <?php if ($section === 'payments'): ?>
            <div class="card"><div class="card-header"><div class="card-title">Gateway URLs to configure</div></div><div class="card-body space-y-3 text-sm">
                <p class="text-slate-600 dark:text-slate-300">These are sent automatically with every payment, but some merchant dashboards also ask for them:</p>
                <?php foreach (['PayFast notify (ITN) URL' => site_url('webhooks/payfast'), 'Ozow notify URL' => site_url('webhooks/ozow'), 'Success URL pattern' => site_url('pay/{token}/success'), 'Cancel URL pattern' => site_url('pay/{token}?cancelled=1')] as $l => $u): ?>
                    <div><div class="label"><?= $l ?></div><code class="block rounded-lg bg-slate-100 px-3 py-2 text-xs dark:bg-white/5"><?= esc($u) ?></code></div>
                <?php endforeach; ?>
                <p class="text-xs text-slate-500">PayFast sandbox credentials (merchant 10000100 / key 46f0cd694581a) are pre-filled for testing. Switch off sandbox and enter your live details to go live. Your site must be on HTTPS for the gateways to post back.</p>
            </div></div>
        <?php endif; ?>

        <?php if ($section === 'notifications'): ?>
            <form method="post" action="<?= site_url('reports/weekly/send') ?>" class="card flex items-center justify-between gap-3 p-5"><?= csrf_field() ?>
                <div class="text-sm"><b>Preview the Monday report</b><br><span class="text-slate-500">Sends last week's report to the recipients above right now.</span></div>
                <button class="btn-dark"><?= icon('zap', 'h-4 w-4') ?> Send now</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
