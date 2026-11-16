<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Book a service<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($done): ?>
    <div class="card mx-auto max-w-lg p-10 text-center">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><?= icon('check', 'h-8 w-8') ?></div>
        <h1 class="text-2xl font-bold text-slate-900">Booking request received</h1>
        <p class="mt-2 text-slate-600">Thank you. Our service team will confirm your booking shortly by email or phone.</p>
        <a href="<?= site_url('book') ?>" class="btn-light mt-6">Book another vehicle</a>
    </div>
<?php else: ?>
<div class="grid gap-8 lg:grid-cols-5">
    <div class="lg:col-span-2">
        <h1 class="text-3xl font-extrabold tracking-tight text-navy-900">Book your vehicle in</h1>
        <p class="mt-3 text-slate-600">Services, repairs, diagnostics, brakes, tyres and more. Choose a time that suits you and we'll confirm.</p>
        <ul class="mt-6 space-y-3 text-sm text-slate-700">
            <?php foreach (['Qualified technicians & computer diagnostics', 'Digital inspection report with photos', 'Approve extra work online, no surprises', 'Track your vehicle live & pay online'] as $f): ?>
                <li class="flex items-center gap-3"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-crimson-100 text-crimson-700"><?= icon('check', 'h-3.5 w-3.5') ?></span><?= $f ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="mt-8 rounded-2xl bg-white p-5 text-sm shadow-soft"><div class="font-bold"><?= esc(setting('company_name')) ?></div><div class="text-slate-500"><?= nl2br(esc(setting('company_address'))) ?><br><?= esc(setting('company_phone')) ?></div><div class="mt-2 text-xs text-slate-500">Mon–Fri 07:30–17:00 · Sat 08:00–13:00</div></div>
    </div>
    <form method="post" action="<?= site_url('book') ?>" class="card space-y-4 p-6 lg:col-span-3"><?= csrf_field() ?>
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Your name *</label><input name="name" value="<?= esc(old('name')) ?>" required class="input"></div>
            <div><label class="label">Mobile *</label><input name="phone" type="tel" value="<?= esc(old('phone')) ?>" required class="input"></div>
            <div class="sm:col-span-2"><label class="label">Email</label><input name="email" type="email" value="<?= esc(old('email')) ?>" class="input"></div>
            <div><label class="label">Registration</label><input name="registration" value="<?= esc(old('registration')) ?>" class="input uppercase" placeholder="CA 123-456"></div>
            <div><label class="label">Vehicle</label><input name="vehicle" value="<?= esc(old('vehicle')) ?>" class="input" placeholder="Toyota Hilux 2.8"></div>
            <div class="sm:col-span-2"><label class="label">What do you need?</label><select name="service" class="input"><?php foreach ($services as $s): ?><option <?= old('service') === $s ? 'selected' : '' ?>><?= esc($s) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Preferred date *</label><input type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= esc(old('date', date('Y-m-d', strtotime('+1 weekday')))) ?>" required class="input"></div>
            <div><label class="label">Drop-off time *</label><select name="time" class="input"><?php foreach (['07:30', '08:00', '08:30', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'] as $t): ?><option <?= old('time') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
            <div class="sm:col-span-2"><label class="label">Anything we should know?</label><textarea name="notes" rows="3" class="input" placeholder="Noises, warning lights, last service…"><?= esc(old('notes')) ?></textarea></div>
        </div>
        <label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" name="popia" value="1" required class="mt-0.5 rounded text-crimson-600"> I agree that <?= esc(setting('company_name')) ?> may store my details to manage this booking (POPIA).</label>
        <button class="btn-primary w-full py-3.5 text-base"><?= icon('calendar', 'h-5 w-5') ?> Request booking</button>
    </form>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
