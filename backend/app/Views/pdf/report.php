<?php /** Generic branded tabular report. @var string $title @var array $columns @var array $rows @var array $meta @var array $totals @var string $logo */ ?>
<html><head><meta charset="utf-8"><?= view('pdf/_style') ?><style>.items td, .items th { font-size: 7.5px; padding: 4px 5px; }</style></head><body>
<?= view('pdf/_header', ['logo' => $logo, 'docTitle' => strtoupper($title), 'docNumber' => '', 'meta' => array_map('esc', ['Generated' => date('d M Y H:i')] + $meta)]) ?>
<table class="items" style="margin-top: 14px;">
    <thead><tr><?php foreach ($columns as $c): ?><th class="<?= in_array($c['type'] ?? '', ['money', 'number'], true) ? 'right' : '' ?>"><?= esc($c['label']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><?php foreach ($columns as $k => $c): $v = $r[$k] ?? ''; $t = $c['type'] ?? 'text'; ?>
            <td class="<?= in_array($t, ['money', 'number'], true) ? 'right' : '' ?>"><?= $t === 'money' ? money($v) : ($t === 'number' && $v !== '' && $v !== null ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') : esc((string) $v)) ?></td>
        <?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>" class="center muted">No data for this period.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($totals): ?>
    <tfoot><tr><?php foreach ($columns as $k => $c): $v = $totals[$k] ?? ''; ?>
        <td class="bold <?= in_array($c['type'] ?? '', ['money', 'number'], true) ? 'right' : '' ?>" style="border-top: 2px solid #0b0d2e; padding: 5px;"><?= ($c['type'] ?? '') === 'money' && $v !== '' ? money($v) : esc((string) $v) ?></td>
    <?php endforeach; ?></tr></tfoot>
    <?php endif; ?>
</table>
<div class="footer"><?= esc(setting('company_legal_name')) ?> · <?= esc($title) ?></div>
</body></html>
