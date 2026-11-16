<?php
/** On-screen "paper" preview of a quote / invoice. @var string $kind @var array $doc @var array $items @var array $customer @var ?array $vehicle */
$title = $kind === 'invoice' ? ((int) setting('vat_registered', 1) === 1 ? 'TAX INVOICE' : 'INVOICE') : 'QUOTATION';
?>
<div class="card overflow-hidden">
    <div class="h-1.5 bg-gradient-to-r from-navy-900 via-crimson-700 to-brand-red"></div>
    <div class="bg-white p-6 text-slate-800 sm:p-10">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="Wheelsturn Automotive" class="h-14 w-auto">
                <div class="mt-3 text-xs leading-relaxed text-slate-500">
                    <div class="font-semibold text-slate-700"><?= esc(setting('company_legal_name')) ?></div>
                    <?= nl2br(esc(setting('company_address'))) ?>
                    <?php if (setting('company_vat_number')): ?><div>VAT No: <?= esc(setting('company_vat_number')) ?></div><?php endif; ?>
                    <?php if (setting('company_reg_number')): ?><div>Reg No: <?= esc(setting('company_reg_number')) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="sm:text-right">
                <div class="text-2xl font-extrabold tracking-tight text-navy-900"><?= $title ?></div>
                <div class="mt-1 font-mono text-sm font-semibold text-crimson-700"><?= esc($doc['number']) ?></div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:justify-end">
                    <dt class="text-slate-500">Date</dt><dd class="font-semibold"><?= fdate($doc['issue_date']) ?></dd>
                    <?php if ($kind === 'invoice'): ?><dt class="text-slate-500">Due</dt><dd class="font-semibold"><?= fdate($doc['due_date']) ?></dd>
                    <?php else: ?><dt class="text-slate-500">Valid until</dt><dd class="font-semibold"><?= fdate($doc['expiry_date']) ?></dd><?php endif; ?>
                    <?php if (!empty($doc['order_number'])): ?><dt class="text-slate-500">Order no.</dt><dd class="font-semibold"><?= esc($doc['order_number']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="mt-8 grid gap-6 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2">
            <div>
                <div class="mb-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">Bill to</div>
                <div class="font-bold"><?= esc(customer_name($customer)) ?></div>
                <div class="text-xs leading-relaxed text-slate-600">
                    <?= esc(implode(', ', array_filter([$customer['address_line1'], $customer['address_line2'], $customer['city'], $customer['postal_code']]))) ?>
                    <?php if ($customer['vat_number']): ?><div>VAT No: <?= esc($customer['vat_number']) ?></div><?php endif; ?>
                    <div><?= esc(implode(' · ', array_filter([$customer['phone'], $customer['email']]))) ?></div>
                </div>
            </div>
            <?php if ($vehicle): ?>
            <div>
                <div class="mb-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">Vehicle</div>
                <div class="font-bold"><?= esc(trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'])) ?></div>
                <div class="text-xs leading-relaxed text-slate-600">Reg: <b><?= esc($vehicle['registration']) ?></b><?= $vehicle['vin'] ? ' · VIN: ' . esc($vehicle['vin']) : '' ?><?= !empty($job['odometer_in']) ? '<br>Odometer: ' . number_format($job['odometer_in']) . ' km' : '' ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="-mx-6 mt-6 overflow-x-auto sm:mx-0">
            <table class="w-full min-w-[560px] text-sm">
                <thead><tr class="border-b-2 border-navy-900 text-left text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="py-2 pl-6 sm:pl-0">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Unit price</th><th class="py-2 text-right">Disc.</th><?php if ((int) setting('vat_registered', 1) === 1): ?><th class="py-2 text-right">VAT</th><?php endif; ?><th class="py-2 pr-6 text-right sm:pr-0">Amount</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="py-2.5 pl-6 sm:pl-0"><div class="font-medium"><?= esc($it['description']) ?></div><div class="text-[11px] text-slate-400"><?= esc(\App\Libraries\LineItems::TYPES[$it['item_type']] ?? '') ?></div></td>
                        <td class="py-2.5 text-right"><?= (float) $it['quantity'] ?></td>
                        <td class="py-2.5 text-right"><?= money($it['unit_price']) ?></td>
                        <td class="py-2.5 text-right"><?= (float) $it['discount_percent'] ? (float) $it['discount_percent'] . '%' : '' ?></td>
                        <?php if ((int) setting('vat_registered', 1) === 1): ?><td class="py-2.5 text-right text-slate-500"><?= money($it['line_vat']) ?></td><?php endif; ?>
                        <td class="py-2.5 pr-6 text-right font-semibold sm:pr-0"><?= money($it['line_subtotal'] - $it['line_discount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:justify-between">
            <div class="max-w-sm text-xs leading-relaxed text-slate-500">
                <?php if (!empty($doc['notes'])): ?><div class="mb-3 whitespace-pre-line text-slate-700"><?= esc($doc['notes']) ?></div><?php endif; ?>
                <?php if ($kind === 'invoice' && setting('bank_account_number')): ?>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="mb-1 font-bold text-slate-700">Banking details</div>
                        <?= esc(setting('bank_name')) ?> · <?= esc(setting('bank_account_name')) ?><br>Acc: <?= esc(setting('bank_account_number')) ?> · Branch: <?= esc(setting('bank_branch_code')) ?><br>Reference: <b><?= esc($doc['number']) ?></b></div>
                <?php endif; ?>
            </div>
            <dl class="w-full space-y-1.5 text-sm sm:w-72">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal (excl. VAT)</dt><dd><?= money($doc['subtotal'] - $doc['discount_total']) ?></dd></div>
                <?php if ((float) $doc['discount_total'] > 0): ?><div class="flex justify-between text-xs"><dt class="text-slate-400">incl. discount</dt><dd class="text-slate-400">-<?= money($doc['discount_total']) ?></dd></div><?php endif; ?>
                <?php if ((int) setting('vat_registered', 1) === 1): ?><div class="flex justify-between"><dt class="text-slate-500">VAT @ <?= (float) setting('vat_rate', 15) ?>%</dt><dd><?= money($doc['vat_total']) ?></dd></div><?php endif; ?>
                <div class="flex justify-between border-t-2 border-navy-900 pt-2 text-lg font-extrabold text-navy-900"><dt>Total</dt><dd><?= money($doc['total']) ?></dd></div>
                <?php if ($kind === 'invoice' && ((float) $doc['amount_paid'] > 0 || (float) ($doc['amount_credited'] ?? 0) > 0)): ?>
                    <?php if ((float) ($doc['amount_credited'] ?? 0) > 0): ?><div class="flex justify-between text-violet-600"><dt>Credited</dt><dd>-<?= money($doc['amount_credited']) ?></dd></div><?php endif; ?>
                    <div class="flex justify-between text-emerald-600"><dt>Paid</dt><dd>-<?= money($doc['amount_paid']) ?></dd></div>
                    <div class="flex justify-between rounded-lg bg-crimson-50 px-2 py-1.5 font-bold text-crimson-800"><dt>Balance due</dt><dd><?= money($doc['balance_due']) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>
