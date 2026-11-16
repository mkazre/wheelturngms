<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Suppliers<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Suppliers', 'subtitle' => 'Parts suppliers, sublet partners and service providers.', 'actions' => '<a href="' . site_url('suppliers/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New supplier</a>']) ?>
<div class="card">
    <form class="flex gap-3 border-b border-slate-100 p-4 dark:border-white/10"><input name="q" value="<?= esc(service('request')->getGet('q')) ?>" placeholder="Search suppliers…" class="input flex-1"><button class="btn-dark">Search</button></form>
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Supplier</th><th>Contact</th><th>VAT no.</th><th>Terms</th><th class="text-center">Items</th><th class="text-right">Total spend</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= site_url('suppliers/' . $r['id'] . '/edit') ?>'">
                <td class="font-semibold"><?= esc($r['name']) ?></td>
                <td><div><?= esc($r['contact_person']) ?></div><div class="text-xs text-slate-500"><?= esc(implode(' · ', array_filter([$r['phone'], $r['email']]))) ?></div></td>
                <td class="font-mono text-xs"><?= esc($r['vat_number'] ?: '—') ?></td>
                <td><?= (int) $r['payment_terms_days'] ?> days</td>
                <td class="text-center"><span class="badge-gray"><?= $r['products'] ?></span></td>
                <td class="text-right font-semibold"><?= money($r['spend']) ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= view('partials/pagination', ['pager' => $pager]) ?>
    <?php else: ?><?= view('partials/empty', ['icon' => 'truck', 'title' => 'No suppliers yet', 'action' => '<a href="' . site_url('suppliers/new') . '" class="btn-primary">Add supplier</a>']) ?><?php endif; ?>
</div>
<?= $this->endSection() ?>
