<?php $isEdit = !empty($c['id']); $v = static fn ($k, $d = '') => esc(old($k, $c[$k] ?? $d)); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit customer' : 'New customer' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit ' . esc(customer_name($c)) : 'New customer', 'crumbs' => ['customers' => 'Customers']]) ?>
<form method="post" action="<?= site_url($isEdit ? 'customers/' . $c['id'] . '/save' : 'customers/save') ?>" x-data="{ type: '<?= $v('type', 'individual') ?>' }" class="grid gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= esc(service('request')->getGet('return_to')) ?>">
    <div class="space-y-6 lg:col-span-2">
        <div class="card"><div class="card-header"><div class="card-title">Customer details</div></div><div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="flex gap-2 sm:col-span-2">
                <?php foreach (['individual' => 'Individual', 'business' => 'Business / Fleet'] as $k => $l): ?>
                    <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition" :class="type === '<?= $k ?>' ? 'border-crimson-500 bg-crimson-50 text-crimson-800 dark:bg-crimson-500/10 dark:text-white' : 'border-slate-200 dark:border-white/10'">
                        <input type="radio" name="type" value="<?= $k ?>" x-model="type" class="text-crimson-600 focus:ring-crimson-500"> <?= $l ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div x-show="type === 'business'" class="sm:col-span-2"><label class="label">Company name *</label><input name="company_name" value="<?= $v('company_name') ?>" class="input"></div>
            <div><label class="label"><span x-text="type === 'business' ? 'Contact first name' : 'First name *'"></span></label><input name="first_name" value="<?= $v('first_name') ?>" class="input"></div>
            <div><label class="label">Last name</label><input name="last_name" value="<?= $v('last_name') ?>" class="input"></div>
            <div><label class="label">Mobile</label><input name="phone" type="tel" value="<?= $v('phone') ?>" class="input" placeholder="082 123 4567"></div>
            <div><label class="label">Alternative phone</label><input name="alt_phone" type="tel" value="<?= $v('alt_phone') ?>" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Email (for invoices &amp; reminders)</label><input name="email" type="email" value="<?= $v('email') ?>" class="input"></div>
            <div x-show="type === 'individual'"><label class="label">ID / Passport number</label><input name="id_number" value="<?= $v('id_number') ?>" class="input"></div>
            <div x-show="type === 'business'"><label class="label">VAT number</label><input name="vat_number" value="<?= $v('vat_number') ?>" class="input" placeholder="4xxxxxxxxx"></div>
            <div x-show="type === 'business'"><label class="label">Company reg. number</label><input name="registration_number" value="<?= $v('registration_number') ?>" class="input" placeholder="2020/123456/07"></div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Address</div></div><div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Street address</label><input name="address_line1" value="<?= $v('address_line1') ?>" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Suburb / complex</label><input name="address_line2" value="<?= $v('address_line2') ?>" class="input"></div>
            <div><label class="label">City</label><input name="city" value="<?= $v('city') ?>" class="input"></div>
            <div><label class="label">Province</label><select name="province" class="input"><option value="">—</option>
                <?php foreach (['Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo', 'Mpumalanga', 'North West', 'Northern Cape', 'Western Cape'] as $p): ?><option <?= old('province', $c['province'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
            </select></div>
            <div><label class="label">Postal code</label><input name="postal_code" value="<?= $v('postal_code') ?>" class="input"></div>
        </div></div>
    </div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Account</div></div><div class="card-body space-y-4">
            <div><label class="label">Payment terms</label><select name="payment_terms_days" class="input">
                <?php foreach ([0 => 'Due on receipt (COD)', 7 => '7 days', 14 => '14 days', 30 => '30 days', 60 => '60 days'] as $d => $l): ?><option value="<?= $d ?>" <?= (int) ($c['payment_terms_days'] ?? 0) === $d ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select></div>
            <div><label class="label">Credit limit (R)</label><input name="credit_limit" type="number" step="0.01" value="<?= $v('credit_limit', 0) ?>" class="input"></div>
            <div><label class="label">How did they find us?</label><select name="source" class="input"><option value="">—</option>
                <?php foreach (['Walk-in', 'Referral', 'Google', 'Facebook', 'Instagram', 'WhatsApp', 'Fleet contract', 'Insurance', 'Returning customer', 'Other'] as $s): ?><option <?= ($c['source'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
            </select></div>
            <div><label class="label">Tags</label><input name="tags" value="<?= $v('tags') ?>" class="input" placeholder="vip, fleet, ..."></div>
            <div><label class="label">Notes</label><textarea name="notes" rows="3" class="input"><?= $v('notes') ?></textarea></div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="marketing_consent" value="1" <?= !empty($c['marketing_consent']) ? 'checked' : '' ?> class="mt-0.5 rounded text-crimson-600 focus:ring-crimson-500"> Customer agrees to receive service reminders &amp; promotions</label>
            <?php if (!$isEdit): ?><label class="flex items-start gap-2 text-sm"><input type="checkbox" name="popia_consent" value="1" checked class="mt-0.5 rounded text-crimson-600 focus:ring-crimson-500"> POPIA consent obtained to store personal information</label><?php endif; ?>
        </div></div>
        <div class="flex gap-2"><button class="btn-primary flex-1"><?= icon('check', 'h-4 w-4') ?> Save customer</button><a href="<?= site_url($isEdit ? 'customers/' . $c['id'] : 'customers') ?>" class="btn-light">Cancel</a></div>
    </div>
</form>
<?= $this->endSection() ?>
