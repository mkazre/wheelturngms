<?php /** @var array $customer @var ?array $vehicle @var ?array $job */ ?>
<table style="margin-top: 14px;">
    <tr>
        <td style="width: 50%; padding-right: 6px; vertical-align: top;">
            <div class="box">
                <div class="label"><?= esc($partyLabel ?? 'Bill to') ?></div>
                <div class="bold" style="font-size: 10px;"><?= esc(customer_name($customer)) ?></div>
                <div class="small">
                    <?= esc(implode(', ', array_filter([$customer['address_line1'], $customer['address_line2']]))) ?><br>
                    <?= esc(implode(', ', array_filter([$customer['city'], $customer['province'], $customer['postal_code']]))) ?><br>
                    <?= esc(implode(' · ', array_filter([$customer['phone'], $customer['email']]))) ?>
                    <?php if ($customer['vat_number']): ?><br>Customer VAT No: <b><?= esc($customer['vat_number']) ?></b><?php endif; ?>
                </div>
            </div>
        </td>
        <td style="width: 50%; padding-left: 6px; vertical-align: top;">
            <?php if (!empty($vehicle)): ?>
            <div class="box">
                <div class="label">Vehicle</div>
                <div class="bold" style="font-size: 10px;"><?= esc(trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['variant'])) ?></div>
                <div class="small">
                    Registration: <b><?= esc($vehicle['registration']) ?></b><?= $vehicle['vin'] ? ' · VIN: ' . esc($vehicle['vin']) : '' ?><br>
                    <?php if (!empty($job['odometer_in'])): ?>Odometer: <?= number_format($job['odometer_in']) ?> km<?php elseif ($vehicle['odometer']): ?>Odometer: <?= number_format($vehicle['odometer']) ?> km<?php endif; ?>
                    <?= $vehicle['colour'] ? ' · ' . esc($vehicle['colour']) : '' ?>
                </div>
            </div>
            <?php endif; ?>
        </td>
    </tr>
</table>
