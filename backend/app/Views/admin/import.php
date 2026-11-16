<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Import data<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Import data', 'subtitle' => 'Moving from another system or a spreadsheet? Import customers first, then vehicles (matched to owners by email or phone), then parts.']) ?>
<div class="grid gap-6 lg:grid-cols-3">
    <?php foreach ($fields as $type => $cols): ?>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('admin/import/upload') ?>" class="card flex flex-col"><?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
            <div class="card-header"><div class="card-title"><?= ucfirst($type) ?></div></div>
            <div class="card-body flex flex-1 flex-col gap-3">
                <p class="text-xs text-slate-500">Columns we can import:</p>
                <div class="flex flex-wrap gap-1"><?php foreach ($cols as $l): ?><span class="badge-gray"><?= esc($l) ?></span><?php endforeach; ?></div>
                <div class="flex-1"></div>
                <input type="file" name="file" accept=".csv,.txt" required class="text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white">
                <button class="btn-primary"><?= icon('upload', 'h-4 w-4') ?> Upload &amp; map columns</button>
            </div>
        </form>
    <?php endforeach; ?>
</div>
<p class="mt-4 text-xs text-slate-500">Duplicates (same email, registration or SKU) are skipped automatically, so it is safe to re-run an import.</p>
<?= $this->endSection() ?>
