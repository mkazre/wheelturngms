<?php $colOpts = static function ($sel, $allowNone = true) use ($cols) {
    $h = $allowNone ? '<option value="">— none —</option>' : '';
    for ($i = 0; $i < $cols; $i++) {
        $h .= '<option value="' . $i . '" ' . ((string) $sel === (string) $i ? 'selected' : '') . '>Column ' . chr(65 + $i) . '</option>';
    }
    return $h;
}; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Map statement<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Map statement columns', 'subtitle' => 'Tell us which column is which. We guessed from the headers, so check them and import.', 'crumbs' => ['accounting' => 'Accounting', 'banking/' . $acc['id'] => $acc['name']]]) ?>
<form method="post" action="<?= site_url('banking/' . $acc['id'] . '/import') ?>" class="space-y-6"><?= csrf_field() ?>
    <input type="hidden" name="file" value="<?= esc($file) ?>">
    <div class="card"><div class="card-body grid gap-4 sm:grid-cols-4 lg:grid-cols-8">
        <div><label class="label">Skip first rows</label><input type="number" name="skip" min="0" value="<?= (int) $guess['skip'] ?>" class="input"></div>
        <div><label class="label">Date *</label><select name="date" class="input"><?= $colOpts($guess['date'], false) ?></select></div>
        <div class="sm:col-span-2"><label class="label">Description *</label><select name="desc" class="input"><?= $colOpts($guess['desc'], false) ?></select></div>
        <div><label class="label">Reference</label><select name="ref" class="input"><?= $colOpts($guess['ref']) ?></select></div>
        <div><label class="label">Amount (±)</label><select name="amount" class="input"><?= $colOpts($guess['amount']) ?></select></div>
        <div><label class="label">…or Debit</label><select name="debit" class="input"><?= $colOpts($guess['debit']) ?></select></div>
        <div><label class="label">…and Credit</label><select name="credit" class="input"><?= $colOpts($guess['credit']) ?></select></div>
        <div><label class="label">Balance</label><select name="balance" class="input"><?= $colOpts($guess['balance']) ?></select></div>
    </div></div>
    <div class="card"><div class="card-header"><div class="card-title">Preview (first rows of the file)</div></div>
        <div class="table-wrap"><table class="table text-xs">
            <thead><tr><th>#</th><?php for ($i = 0; $i < $cols; $i++): ?><th>Col <?= chr(65 + $i) ?></th><?php endfor; ?></tr></thead>
            <tbody><?php foreach ($rows as $n => $r): ?><tr class="<?= $n < $guess['skip'] ? 'opacity-40' : '' ?>"><td class="text-slate-400"><?= $n + 1 ?></td><?php for ($i = 0; $i < $cols; $i++): ?><td class="max-w-xs truncate"><?= esc($r[$i] ?? '') ?></td><?php endfor; ?></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="flex justify-end gap-2"><a href="<?= site_url('banking/' . $acc['id']) ?>" class="btn-light">Cancel</a><button class="btn-primary"><?= icon('upload', 'h-4 w-4') ?> Import &amp; auto-match</button></div>
</form>
<?= $this->endSection() ?>
