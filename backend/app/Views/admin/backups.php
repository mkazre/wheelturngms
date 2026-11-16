<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Backups<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Database backups', 'subtitle' => 'A compressed backup runs every night through the cron job; the last 14 are kept. Download one regularly and store it off the server.',
    'actions' => '<form method="post" action="' . site_url('admin/backups/run') . '">' . csrf_field() . '<button class="btn-primary">' . icon('download', 'h-4 w-4') . ' Back up now</button></form>']) ?>
<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Backup</th><th>Created</th><th class="text-right">Size</th><th></th></tr></thead>
    <tbody><?php foreach ($files as $f): ?>
        <tr><td class="font-mono text-xs"><?= esc($f['name']) ?></td><td><?= date('d M Y H:i', $f['time']) ?> <span class="text-xs text-slate-400">(<?= time_ago(date('Y-m-d H:i:s', $f['time'])) ?>)</span></td><td class="text-right"><?= number_to_size($f['size']) ?></td>
            <td class="text-right"><?php if (session('role') === 'admin'): ?><a href="<?= site_url('admin/backups/' . $f['name']) ?>" class="btn-light btn-sm"><?= icon('download', 'h-3.5 w-3.5') ?> Download</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$files): ?><tr><td colspan="4" class="py-10 text-center text-slate-500">No backups yet. Click "Back up now".</td></tr><?php endif; ?></tbody>
</table></div></div>
<p class="mt-4 text-xs text-slate-500">Backups contain all business data (customers, invoices, ledger). Uploaded photos and slips live in <code>writable/uploads</code> and are covered by cPanel's full-account backups. To restore, import the .sql.gz file in cPanel → phpMyAdmin.</p>
<?= $this->endSection() ?>
