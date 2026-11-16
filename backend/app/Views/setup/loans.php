<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Loan Vehicles<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Courtesy &amp; loan vehicles', 'subtitle' => 'Track which customer has which courtesy car, when it is due back, mileage and fuel.']) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-4">
        <?php foreach ($vehicles as $v): ?>
            <div class="card p-4" x-data="{ edit: false }">
                <div class="flex items-center justify-between">
                    <div><span class="rounded border-2 border-slate-800 bg-amber-300 px-2 font-mono text-xs font-bold text-slate-900"><?= esc($v['registration']) ?></span><div class="mt-1 text-sm font-semibold"><?= esc($v['description']) ?></div><div class="text-xs text-slate-500"><?= $v['odometer'] ? number_format($v['odometer']) . ' km' : '' ?></div></div>
                    <span class="<?= $v['status'] === 'available' ? 'badge-green' : 'badge-amber' ?>"><?= $v['status'] === 'available' ? 'Available' : 'Out' ?></span>
                </div>
                <?php if ($v['status'] === 'available'): ?>
                    <form method="post" action="<?= site_url('loans/out') ?>" class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-white/10"><?= csrf_field() ?><input type="hidden" name="loan_vehicle_id" value="<?= $v['id'] ?>">
                        <select name="customer_id" data-select data-remote="<?= site_url('customers/lookup') ?>" class="input" required></select>
                        <div class="grid grid-cols-2 gap-2"><input type="datetime-local" name="due_at" class="input text-xs"><input name="licence_number" placeholder="Driver's licence no." class="input text-xs"></div>
                        <button class="btn-dark btn-sm w-full">Book out</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?= site_url('loans/vehicle') ?>" class="card space-y-3 p-4"><?= csrf_field() ?>
            <div class="card-title">Add loan vehicle</div>
            <div class="grid grid-cols-2 gap-2"><input name="registration" placeholder="Registration" class="input" required><input name="odometer" type="number" placeholder="Odometer" class="input"></div>
            <input name="description" placeholder="e.g. 2022 VW Polo Vivo (white)" class="input">
            <button class="btn-primary btn-sm w-full">Add vehicle</button>
        </form>
    </div>
    <div class="card xl:col-span-2"><div class="card-header"><div class="card-title">Bookings</div></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Vehicle</th><th>Customer</th><th>Job</th><th>Out</th><th>Due</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($bookings as $b): $late = !$b['returned_at'] && $b['due_at'] && strtotime($b['due_at']) < time(); ?>
                <tr><td class="font-mono text-xs font-bold"><?= esc($b['registration']) ?></td><td><?= esc(customer_name($b)) ?><div class="text-xs text-slate-500"><?= esc($b['phone']) ?></div></td><td><?= $b['job_card_id'] ? '<a class="text-crimson-700" href="' . site_url('job-cards/' . $b['job_card_id']) . '">' . esc($b['job_number']) . '</a>' : '—' ?></td>
                    <td><?= fdatetime($b['out_at']) ?></td><td class="<?= $late ? 'font-semibold text-red-600' : '' ?>"><?= fdatetime($b['due_at']) ?></td>
                    <td><?php if ($b['returned_at']): ?><span class="badge-gray">Returned <?= fdate($b['returned_at']) ?></span><?php else: ?>
                        <form method="post" action="<?= site_url('loans/' . $b['id'] . '/return') ?>" class="flex gap-1"><?= csrf_field() ?><input name="odometer_in" type="number" placeholder="km in" class="input w-24 py-1 text-xs"><button class="btn-dark btn-sm">Return</button></form>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$bookings): ?><tr><td colspan="6" class="py-8 text-center text-slate-500">No loan bookings yet.</td></tr><?php endif; ?></tbody>
        </table></div>
    </div>
</div>
<?= $this->endSection() ?>
