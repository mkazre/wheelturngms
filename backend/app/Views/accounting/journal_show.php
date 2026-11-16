<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($e['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Journal ' . esc($e['number']) . ($e['reversed_by'] ? ' <span class="badge-red">Reversed</span>' : '') . ($e['is_reversal'] ? ' <span class="badge-purple">Reversal</span>' : ''),
    'subtitle' => esc($e['description']), 'crumbs' => ['accounting' => 'Accounting', 'accounting/journals' => 'Journals'],
    'actions' => $sourceUrl ? '<a href="' . site_url($sourceUrl) . '" class="btn-light">Open source document →</a>' : '']) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2"><div class="table-wrap"><table class="table">
        <thead><tr><th>Account</th><th>Description</th><th>Customer / supplier</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
        <tbody><?php foreach ($lines as $l): ?>
            <tr><td><a href="<?= site_url('accounting/ledger/' . $l['account_id']) ?>" class="font-semibold hover:text-crimson-700"><span class="font-mono text-xs text-slate-500"><?= esc($l['code']) ?></span> <?= esc($l['name']) ?></a></td>
                <td class="text-slate-500"><?= esc($l['description']) ?></td>
                <td class="text-slate-500"><?= esc($l['supplier_name'] ?: trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? ''))) ?></td>
                <td class="text-right"><?= (float) $l['debit'] ? money($l['debit']) : '' ?></td><td class="text-right"><?= (float) $l['credit'] ? money($l['credit']) : '' ?></td></tr>
        <?php endforeach; ?></tbody>
        <tfoot><tr class="border-t-2 border-navy-900 dark:border-white/30"><td colspan="3" class="px-4 py-3 font-bold">Totals</td><td class="px-4 py-3 text-right font-bold"><?= money(array_sum(array_column($lines, 'debit'))) ?></td><td class="px-4 py-3 text-right font-bold"><?= money(array_sum(array_column($lines, 'credit'))) ?></td></tr></tfoot>
    </table></div></div>
    <div class="space-y-6">
        <div class="card"><dl class="card-body space-y-3 text-sm">
            <?php foreach (['Date' => fdate($e['entry_date']), 'Reference' => $e['reference'], 'Source' => ucwords(str_replace('_', ' ', $e['source_type'])), 'Posted by' => trim(($e['first_name'] ?? 'System') . ' ' . ($e['last_name'] ?? '')), 'Posted at' => fdatetime($e['created_at'])] as $k => $v): if (!$v) continue; ?>
                <div class="flex justify-between gap-3"><dt class="text-slate-500"><?= $k ?></dt><dd class="text-right font-semibold"><?= esc($v) ?></dd></div>
            <?php endforeach; ?>
            <?php if ($e['memo']): ?><div><dt class="text-slate-500">Memo</dt><dd class="mt-1"><?= esc($e['memo']) ?></dd></div><?php endif; ?>
            <?php if ($e['reversed_by']): ?><div><a href="<?= site_url('accounting/journals/' . $e['reversed_by']) ?>" class="font-semibold text-crimson-700">View reversing journal →</a></div><?php endif; ?>
        </dl></div>
        <?php if ($files): ?><div class="card"><div class="card-header"><div class="card-title">Supporting documents</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'journal', 'entityId' => $e['id']]) ?></div></div><?php endif; ?>
        <?php if (in_array($e['source_type'], ['manual', 'opening', 'bank'], true) && !$e['reversed_by'] && !$e['is_reversal']): ?>
            <form method="post" action="<?= site_url('accounting/journals/' . $e['id'] . '/reverse') ?>" data-confirm="Post a reversing journal for this entry?" class="card flex items-end gap-2 p-4"><?= csrf_field() ?>
                <div class="flex-1"><label class="label">Reversal date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" class="input"></div><button class="btn-dark">Reverse</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
