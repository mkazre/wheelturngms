<?php /** @var string $logo @var string $docTitle @var string $docNumber @var array $meta [label => value] */ $s = static fn ($k) => esc(setting($k)); ?>
<div class="bar"><span></span></div>
<table>
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <?php if ($logo): ?><img src="<?= $logo ?>" style="height: 58px;"><?php endif; ?>
            <div class="small muted" style="margin-top: 6px;">
                <b style="color:#1e293b"><?= $s('company_legal_name') ?></b><br>
                <?= nl2br($s('company_address')) ?><br>
                <?= implode(' · ', array_filter([$s('company_phone'), $s('company_email'), $s('company_website')])) ?><br>
                <?php if (setting('company_vat_number')): ?>VAT No: <b><?= $s('company_vat_number') ?></b> <?php endif; ?>
                <?php if (setting('company_reg_number')): ?>· Reg No: <?= $s('company_reg_number') ?><?php endif; ?>
                <?php if (setting('company_rmi_number')): ?>· RMI: <?= $s('company_rmi_number') ?><?php endif; ?>
            </div>
        </td>
        <td style="width: 45%; vertical-align: top;" class="right">
            <div class="title"><?= esc($docTitle) ?></div>
            <div class="docno"><?= esc($docNumber) ?></div>
            <table style="margin-top: 8px; width: auto; margin-left: auto;">
                <?php foreach ($meta as $k => $v): if ($v === null || $v === '') continue; ?>
                    <tr><td class="muted right" style="padding: 1px 8px;"><?= esc($k) ?></td><td class="bold right" style="padding: 1px 0;"><?= $v ?></td></tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
</table>
