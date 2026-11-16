<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Pricing matrix<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Parts pricing matrix', 'subtitle' => 'Cheap parts carry higher markup, expensive parts lower, the way top workshops price. Sell prices round to the nearest R5 above R100.', 'crumbs' => ['inventory' => 'Inventory']]) ?>
<div class="grid gap-6 lg:grid-cols-2">
    <form method="post" action="<?= site_url('stock/pricing') ?>" class="card"><?= csrf_field() ?>
        <div class="card-body space-y-3">
            <label class="label">Cost range = markup % (one band per line, * = and above)</label>
            <textarea name="markup_matrix" rows="8" data-no-voice class="input font-mono text-sm" placeholder="0-100=120&#10;100.01-500=80&#10;500.01-1500=55&#10;1500.01-5000=40&#10;5000.01-*=30"><?= esc($matrix) ?></textarea>
            <div class="flex flex-wrap gap-2"><button class="btn-dark">Save matrix</button><button name="apply" value="1" class="btn-primary" onclick="return confirm('Re-price every stocked part from its cost using this matrix?')">Save &amp; re-price all parts</button></div>
        </div>
    </form>
    <div class="card"><div class="card-header"><div class="card-title">Preview</div></div>
        <div class="table-wrap"><table class="table"><thead><tr><th class="text-right">Cost</th><th class="text-right">Sell (excl.)</th><th class="text-right">Margin</th></tr></thead><tbody>
            <?php foreach ($preview as $cost => $sell): ?><tr><td class="text-right"><?= money($cost) ?></td><td class="text-right font-semibold"><?= $sell !== null ? money($sell) : '<span class="text-slate-400">no band</span>' ?></td><td class="text-right"><?= $sell ? round(($sell - $cost) / $sell * 100) . '%' : '' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<?= $this->endSection() ?>
