<?php
use App\Libraries\LineItems;

/** @var array $items @var array $doc @var bool $showPrices */
$showPrices = $showPrices ?? true;
$vatOn = (int) setting('vat_registered', 1) === 1;
?>
<table class="items" style="margin-top: 14px;">
    <thead><tr>
        <th style="width: 6%;">#</th><th>Description</th><th class="right" style="width: 8%;">Qty</th>
        <?php if ($showPrices): ?><th class="right" style="width: 13%;">Unit (excl.)</th><th class="right" style="width: 7%;">Disc.</th><?php if ($vatOn): ?><th class="right" style="width: 11%;">VAT</th><?php endif; ?><th class="right" style="width: 14%;">Amount (excl.)</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
        <tr>
            <td class="muted"><?= $i + 1 ?></td>
            <td><?= esc($it['description']) ?><br><span class="small muted"><?= esc(LineItems::TYPES[$it['item_type']] ?? '') ?><?= !empty($it['sku']) ? ' · ' . esc($it['sku']) : '' ?></span></td>
            <td class="right"><?= rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.') ?></td>
            <?php if ($showPrices): ?>
                <td class="right"><?= money($it['unit_price']) ?></td>
                <td class="right"><?= (float) $it['discount_percent'] ? (float) $it['discount_percent'] . '%' : '' ?></td>
                <?php if ($vatOn): ?><td class="right muted"><?= money($it['line_vat']) ?></td><?php endif; ?>
                <td class="right bold"><?= money($it['line_subtotal'] - $it['line_discount']) ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="7" class="center muted">No items</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($showPrices): ?>
<table style="margin-top: 10px;">
    <tr>
        <td style="width: 55%; vertical-align: top; padding-right: 14px;">
            <?= $left ?? '' ?>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="totals">
                <tr><td class="muted">Subtotal (excl. VAT)</td><td class="right"><?= money($doc['subtotal'] - $doc['discount_total']) ?></td></tr>
                <?php if ((float) $doc['discount_total'] > 0): ?><tr><td class="muted small">Discount included</td><td class="right small muted">-<?= money($doc['discount_total']) ?></td></tr><?php endif; ?>
                <?php if ($vatOn): ?><tr><td class="muted">VAT @ <?= (float) setting('vat_rate', 15) ?>%</td><td class="right"><?= money($doc['vat_total']) ?></td></tr><?php endif; ?>
                <tr class="grand"><td>TOTAL<?= $vatOn ? ' (incl. VAT)' : '' ?></td><td class="right"><?= money($doc['total']) ?></td></tr>
                <?= $extraTotals ?? '' ?>
            </table>
        </td>
    </tr>
</table>
<?php endif; ?>
