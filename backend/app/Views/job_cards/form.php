<?php

use App\Controllers\JobCards;

$isEdit = !empty($job['id']);
$val = static fn ($k, $d = '') => esc(old($k, $job[$k] ?? $d));
$dt = static fn ($k) => !empty($job[$k]) ? date('Y-m-d\TH:i', strtotime($job[$k])) : '';
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $isEdit ? 'Edit ' . esc($job['number']) : 'New job card' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => $isEdit ? 'Edit job card ' . esc($job['number']) : 'New job card', 'subtitle' => 'Check the vehicle in, record the complaint, and plan the work.', 'crumbs' => ['job-cards' => 'Job cards']]) ?>

<form method="post" action="<?= site_url($isEdit ? 'job-cards/' . $job['id'] . '/save' : 'job-cards/save') ?>" class="grid gap-6 xl:grid-cols-3">
    <?= csrf_field() ?>
    <div class="space-y-6 xl:col-span-2">
        <div class="card"><div class="card-header"><div class="card-title">Customer &amp; vehicle</div></div><div class="card-body grid gap-4 sm:grid-cols-2">
            <?= view('partials/customer_vehicle', ['customer' => $customer, 'vehicle' => $vehicle, 'returnTo' => 'job-cards/new', 'vehicleRequired' => true]) ?>
            <div><label class="label">Odometer in (km)</label><input id="odometer_in" name="odometer_in" type="number" value="<?= $val('odometer_in', $vehicle['odometer'] ?? '') ?>" class="input"></div>
            <div><label class="label">Fuel level</label>
                <div class="flex gap-1"><?php foreach (JobCards::FUEL as $f): ?>
                    <label class="flex-1"><input type="radio" name="fuel_level" value="<?= $f ?>" class="peer sr-only" <?= ($job['fuel_level'] ?? '') === $f ? 'checked' : '' ?>><span class="block cursor-pointer rounded-lg border border-slate-200 py-2 text-center text-xs font-semibold peer-checked:border-crimson-500 peer-checked:bg-crimson-50 peer-checked:text-crimson-800 dark:border-white/10 dark:peer-checked:bg-crimson-500/10 dark:peer-checked:text-white"><?= $f ?></span></label>
                <?php endforeach; ?></div>
            </div>
        </div></div>

        <div class="card"><div class="card-header"><div class="card-title">Job details</div></div><div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Customer complaint / request</label><textarea name="customer_complaint" rows="3" class="input" placeholder="e.g. Grinding noise when braking, due for 60 000 km service"><?= $val('customer_complaint') ?></textarea></div>
            <div class="sm:col-span-2"><label class="label">Diagnosis / technician findings</label><textarea name="diagnosis" rows="3" class="input"><?= $val('diagnosis') ?></textarea></div>
            <div class="sm:col-span-2"><label class="label">Work performed (prints on invoice)</label><textarea name="work_performed" rows="3" class="input"><?= $val('work_performed') ?></textarea></div>
            <div class="sm:col-span-2"><label class="label">Recommendations for next visit</label><textarea name="recommendations" rows="2" class="input"><?= $val('recommendations') ?></textarea></div>
            <div class="sm:col-span-2"><label class="label">Internal notes (not shown to customer)</label><textarea name="internal_notes" rows="2" class="input"><?= $val('internal_notes') ?></textarea></div>
        </div></div>

        <?= view('partials/line_items_editor', ['lines' => $lines, 'mechanics' => $mechanics]) ?>
    </div>

    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Scheduling</div></div><div class="card-body space-y-4">
            <?php if (!$isEdit): ?>
            <div><label class="label">Initial status</label><select name="status" class="input"><option value="checked_in">Vehicle is here – check in now</option><option value="booked" <?= ($job['status'] ?? '') === 'booked' ? 'selected' : '' ?>>Booking for later</option></select></div>
            <?php endif; ?>
            <div><label class="label">Booked for</label><input type="datetime-local" name="booked_for" value="<?= $dt('booked_for') ?: date('Y-m-d\TH:i') ?>" class="input"></div>
            <div><label class="label">Promised by</label><input type="datetime-local" name="promised_at" value="<?= $dt('promised_at') ?>" class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Priority</label><select name="priority" class="input"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $k => $l): ?><option value="<?= $k ?>" <?= ($job['priority'] ?? 'normal') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div><label class="label">Bay / lift</label><input name="bay" value="<?= $val('bay') ?>" class="input" placeholder="Bay 2"></div>
            </div>
            <div><label class="label">Job type</label><select name="job_type" class="input"><option value="">—</option><?php foreach (JobCards::JOB_TYPES as $t): ?><option <?= ($job['job_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Team</div></div><div class="card-body space-y-4">
            <div><label class="label">Service advisor</label><select name="advisor_id" class="input"><option value="">—</option><?php foreach ($advisors as $a): ?><option value="<?= $a['id'] ?>" <?= (int) ($job['advisor_id'] ?? session('user_id')) === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['first_name'] . ' ' . $a['last_name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="label">Assigned technicians</label><select name="mechanics[]" multiple data-select class="input"><?php foreach ($mechanics as $m): ?><option value="<?= $m['id'] ?>" <?= in_array($m['id'], $assigned) ? 'selected' : '' ?>><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></option><?php endforeach; ?></select></div>
        </div></div>
        <div class="sticky top-20 flex gap-2"><button class="btn-primary flex-1 py-3"><?= icon('check', 'h-4 w-4') ?> <?= $isEdit ? 'Save changes' : 'Open job card' ?></button><a href="<?= site_url($isEdit ? 'job-cards/' . $job['id'] : 'job-cards') ?>" class="btn-light">Cancel</a></div>
    </div>
</form>
<?= $this->endSection() ?>
