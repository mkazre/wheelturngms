<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Checklists<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Technician checklists', 'subtitle' => 'Step-by-step checklists attach automatically to new job cards of the matching job type. Leave the job type blank to apply to every job.']) ?>
<div class="grid gap-6 lg:grid-cols-2">
    <?php foreach (array_merge($rows, [['id' => 0, 'name' => '', 'job_type' => '', 'items' => '', 'is_active' => 1]]) as $r): ?>
        <form method="post" action="<?= site_url('checklists/save') ?>" class="card"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <div class="card-header"><div class="card-title flex items-center gap-2"><?= icon($r['id'] ? 'clipboard' : 'plus', 'h-4 w-4 text-crimson-600') ?> <?= $r['id'] ? esc($r['name']) : 'New checklist' ?></div><?= $r['id'] ? '<span class="badge-gray">' . count(array_filter(preg_split('/\r?\n/', (string) $r['items']))) . ' steps</span>' : '' ?></div>
            <div class="card-body space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Name</label><input name="name" value="<?= esc($r['name']) ?>" class="input" placeholder="Major service checklist"></div>
                    <div><label class="label">Job type</label><select name="job_type" class="input"><option value="">All jobs</option><?php foreach (\App\Controllers\JobCards::JOB_TYPES as $t): ?><option <?= $r['job_type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
                </div>
                <div><label class="label">Steps (one per line)</label><textarea name="items" rows="6" class="input text-sm" placeholder="Drain engine oil&#10;Replace oil filter&#10;Torque wheel nuts to spec&#10;Road test"><?= esc($r['items']) ?></textarea></div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= $r['is_active'] ? 'checked' : '' ?> class="rounded text-crimson-600"> Active</label>
                    <div class="flex gap-2"><?php if ($r['id']): ?><button name="delete" value="1" class="btn-ghost btn-sm text-red-600" onclick="return confirm('Delete checklist?')">Delete</button><?php endif; ?><button class="btn-primary btn-sm">Save</button></div>
                </div>
            </div>
        </form>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
