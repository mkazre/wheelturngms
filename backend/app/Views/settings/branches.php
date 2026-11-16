<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Branches<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Branches: run multiple Wheelsturn locations from one system.']) ?>
<div class="grid gap-6 lg:grid-cols-4">
    <?= view('settings/_nav', ['sections' => $sections, 'section' => $section]) ?>
    <div class="space-y-4 lg:col-span-3">
        <?php foreach (array_merge($branches, [['id' => 0, 'name' => '', 'code' => '', 'address' => '', 'phone' => '', 'email' => '', 'is_active' => 1]]) as $b): ?>
            <form method="post" action="<?= site_url('settings/branches/save') ?>" class="card" x-data="{ open: <?= $b['id'] ? 'false' : 'false' ?> }"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <button type="button" @click="open = !open" class="card-header w-full text-left">
                    <div class="card-title flex items-center gap-2"><?= icon($b['id'] ? 'building' : 'plus', 'h-4 w-4 text-crimson-600') ?> <?= $b['id'] ? esc($b['name']) : 'Add a branch' ?></div>
                    <?= $b['id'] ? ($b['is_active'] ? '<span class="badge-green">Active</span>' : '<span class="badge-gray">Inactive</span>') : '' ?>
                </button>
                <div x-show="open" x-cloak class="card-body grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Name</label><input name="name" value="<?= esc($b['name']) ?>" class="input" required></div>
                    <div><label class="label">Code</label><input name="code" value="<?= esc($b['code']) ?>" class="input"></div>
                    <div><label class="label">Phone</label><input name="phone" value="<?= esc($b['phone']) ?>" class="input"></div>
                    <div><label class="label">Email</label><input name="email" type="email" value="<?= esc($b['email']) ?>" class="input"></div>
                    <div class="sm:col-span-2"><label class="label">Address</label><textarea name="address" rows="2" class="input"><?= esc($b['address']) ?></textarea></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= $b['is_active'] ? 'checked' : '' ?> class="rounded text-crimson-600"> Active</label>
                    <div class="text-right"><button class="btn-primary">Save branch</button></div>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
