<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Vehicles<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => 'Vehicles', 'subtitle' => number_format($pager['total']) . ' vehicles on record · <a class="font-semibold text-crimson-700 hover:underline dark:text-crimson-400" href="?due=1">' . $dueSoon . ' due for service within 30 days</a>',
    'actions' => view('partials/export_buttons', ['base' => 'vehicles']) . '<a href="' . site_url('vehicles/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Add vehicle</a>',
]) ?>
<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10">
        <input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search registration, VIN, make, model or owner…" class="input flex-1">
        <button class="btn-dark">Search</button>
    </form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Registration</th><th>Vehicle</th><th>Owner</th><th class="text-right">Odometer</th><th>Next service</th><th>Last visit</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $due = $r['next_service_date'] && $r['next_service_date'] <= date('Y-m-d', strtotime('+30 days')); ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('vehicles/' . $r['id']) ?>'">
                <td><span class="rounded-md border-2 border-slate-800 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold text-slate-900"><?= esc($r['registration']) ?></span></td>
                <td><div class="font-semibold"><?= esc(trim($r['year'] . ' ' . $r['make'] . ' ' . $r['model'])) ?></div><div class="font-mono text-[11px] text-slate-500"><?= esc($r['vin']) ?></div></td>
                <td><?= esc(customer_name($r)) ?></td>
                <td class="text-right"><?= $r['odometer'] ? number_format($r['odometer']) . ' km' : '—' ?></td>
                <td><?= $r['next_service_date'] ? '<span class="' . ($due ? 'badge-amber' : 'badge-gray') . '">' . fdate($r['next_service_date']) . '</span>' : '—' ?></td>
                <td class="text-slate-500"><?= $r['last_visit'] ? time_ago($r['last_visit']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?>
        <?= view('partials/empty', ['icon' => 'car', 'title' => 'No vehicles found', 'action' => '<a href="' . site_url('vehicles/new') . '" class="btn-primary">Add vehicle</a>']) ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
