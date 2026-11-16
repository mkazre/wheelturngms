<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc(customer_name($c)) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $actions = '<a href="' . site_url('customers/' . $c['id'] . '/statement') . '" target="_blank" class="btn-light">' . icon('file', 'h-4 w-4') . ' Statement</a>'
    . '<a href="' . site_url('customers/' . $c['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>'
    . '<a href="' . site_url('job-cards/new?customer_id=' . $c['id']) . '" class="btn-primary">' . icon('wrench', 'h-4 w-4') . ' New job card</a>'; ?>
<?= view('partials/page_header', ['title' => esc(customer_name($c)), 'subtitle' => esc($c['code']) . ' · Customer since ' . fdate($c['created_at']) . ($c['source'] ? ' · via ' . esc($c['source']) : ''), 'crumbs' => ['customers' => 'Customers'], 'actions' => $actions]) ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Lifetime value', 'value' => money($stats['lifetime']), 'icon' => 'chart', 'tone' => 'crimson']) ?>
    <?= view('partials/kpi', ['label' => 'Balance owing', 'value' => money($stats['balance']), 'icon' => 'wallet', 'tone' => $stats['balance'] > 0 ? 'amber' : 'green']) ?>
    <?= view('partials/kpi', ['label' => 'Visits', 'value' => $stats['visits'], 'icon' => 'wrench', 'tone' => 'navy', 'hint' => $stats['last'] ? 'last ' . time_ago($stats['last']) : '']) ?>
    <?= view('partials/kpi', ['label' => 'Vehicles', 'value' => count($vehicles), 'icon' => 'car', 'tone' => 'sky']) ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Contact</div><span class="<?= $c['type'] === 'business' ? 'badge-navy' : 'badge-gray' ?>"><?= $c['type'] === 'business' ? 'Business' : 'Individual' ?></span></div>
            <dl class="card-body space-y-3 text-sm">
                <?php foreach (['Phone' => $c['phone'], 'Alt. phone' => $c['alt_phone'], 'Email' => $c['email'], 'VAT no.' => $c['vat_number'], 'Reg. no.' => $c['registration_number'], 'ID no.' => $c['id_number'], 'Terms' => $c['payment_terms_days'] ? $c['payment_terms_days'] . ' days' : 'COD', 'Credit limit' => (float) $c['credit_limit'] ? money($c['credit_limit']) : null] as $k => $val): if (!$val) continue; ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500"><?= $k ?></dt><dd class="text-right font-medium"><?= esc($val) ?></dd></div>
                <?php endforeach; ?>
                <?php $addr = array_filter([$c['address_line1'], $c['address_line2'], $c['city'], $c['province'], $c['postal_code']]); if ($addr): ?>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Address</dt><dd class="text-right font-medium"><?= esc(implode(', ', $addr)) ?></dd></div>
                <?php endif; ?>
            </dl>
            <div class="flex flex-wrap gap-2 border-t border-slate-100 p-4 dark:border-white/10">
                <?php if ($c['phone']): $wa = preg_replace('/\D/', '', $c['phone']); $wa = str_starts_with($wa, '0') ? '27' . substr($wa, 1) : $wa; ?>
                    <a href="tel:<?= esc($c['phone']) ?>" class="btn-light btn-sm"><?= icon('phone', 'h-3.5 w-3.5') ?> Call</a>
                    <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" class="btn-light btn-sm">WhatsApp</a>
                <?php endif; ?>
                <?php if ($c['email']): ?><a href="mailto:<?= esc($c['email']) ?>" class="btn-light btn-sm"><?= icon('mail', 'h-3.5 w-3.5') ?> Email</a><?php endif; ?>
            </div>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Customer app &amp; portal</div><?= $portal ? '<span class="badge-green">Active</span>' : '<span class="badge-gray">No access</span>' ?></div>
            <div class="card-body text-sm text-slate-600 dark:text-slate-300">
                <p>Let the customer track repairs, approve quotes and pay invoices from their phone.</p>
                <form method="post" action="<?= site_url('customers/' . $c['id'] . '/portal-access') ?>" class="mt-3"><?= csrf_field() ?><button class="btn-dark btn-sm w-full"><?= $portal ? 'Reset & re-send login' : 'Send app / portal invite' ?></button></form>
            </div>
        </div>
        <?php if ($c['notes'] || $c['tags']): ?>
        <div class="card"><div class="card-header"><div class="card-title">Notes</div></div><div class="card-body text-sm">
            <?php foreach (array_filter(array_map('trim', explode(',', (string) $c['tags']))) as $t): ?><span class="badge-purple mr-1"><?= esc($t) ?></span><?php endforeach; ?>
            <p class="mt-2 whitespace-pre-line text-slate-600 dark:text-slate-300"><?= esc($c['notes']) ?></p>
        </div></div>
        <?php endif; ?>
    </div>

    <div class="xl:col-span-2" x-data="{ tab: 'vehicles' }">
        <div class="card">
            <div class="tabs px-3">
                <?php foreach (['vehicles' => 'Vehicles (' . count($vehicles) . ')', 'jobs' => 'Job cards (' . count($jobs) . ')', 'quotes' => 'Quotes (' . count($quotes) . ')', 'invoices' => 'Invoices (' . count($invoices) . ')', 'payments' => 'Payments', 'files' => 'Files (' . count($files) . ')', 'activity' => 'Activity'] as $k => $l): ?>
                    <button @click="tab = '<?= $k ?>'" class="tab" :class="tab === '<?= $k ?>' && 'active'"><?= $l ?></button>
                <?php endforeach; ?>
            </div>
            <div x-show="tab === 'vehicles'" class="p-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach ($vehicles as $v): ?>
                        <a href="<?= site_url('vehicles/' . $v['id']) ?>" class="group rounded-2xl border border-slate-200 p-4 transition hover:border-crimson-300 hover:shadow-soft dark:border-white/10">
                            <div class="flex items-start justify-between">
                                <div><div class="font-semibold"><?= esc(trim($v['year'] . ' ' . $v['make'] . ' ' . $v['model'])) ?></div><div class="text-xs text-slate-500"><?= esc($v['variant'] ?: $v['colour']) ?></div></div>
                                <span class="rounded-md border-2 border-slate-800 bg-amber-300 px-2 py-0.5 font-mono text-xs font-bold text-slate-900"><?= esc($v['registration']) ?></span>
                            </div>
                            <div class="mt-3 flex gap-4 text-xs text-slate-500"><span><?= $v['odometer'] ? number_format($v['odometer']) . ' km' : '—' ?></span><?php if ($v['next_service_date']): ?><span>Service due <?= fdate($v['next_service_date']) ?></span><?php endif; ?></div>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?= site_url('vehicles/new?customer_id=' . $c['id']) ?>" class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 p-4 text-sm font-semibold text-slate-500 hover:border-crimson-300 hover:text-crimson-700 dark:border-white/10"><?= icon('plus', 'h-4 w-4') ?> Add vehicle</a>
                </div>
            </div>
            <div x-show="tab === 'jobs'" x-cloak class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Vehicle</th><th>Status</th><th>Date</th><th class="text-right">Total</th></tr></thead><tbody>
                <?php foreach ($jobs as $j): ?><tr class="cursor-pointer" onclick="location.href='<?= site_url('job-cards/' . $j['id']) ?>'"><td class="font-semibold"><?= esc($j['number']) ?></td><td><?= esc($j['make'] . ' ' . $j['model']) ?> <span class="text-xs text-slate-500"><?= esc($j['registration']) ?></span></td><td><?= status_badge($j['status']) ?></td><td><?= fdate($j['created_at']) ?></td><td class="text-right"><?= money($j['total']) ?></td></tr><?php endforeach; ?>
                <?php if (!$jobs): ?><tr><td colspan="5" class="py-8 text-center text-slate-500">No job cards yet.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div x-show="tab === 'quotes'" x-cloak class="table-wrap"><table class="table"><thead><tr><th>Quote</th><th>Status</th><th>Date</th><th class="text-right">Total</th></tr></thead><tbody>
                <?php foreach ($quotes as $q): ?><tr class="cursor-pointer" onclick="location.href='<?= site_url('quotes/' . $q['id']) ?>'"><td class="font-semibold"><?= esc($q['number']) ?></td><td><?= status_badge($q['status'], 'quote') ?></td><td><?= fdate($q['issue_date']) ?></td><td class="text-right"><?= money($q['total']) ?></td></tr><?php endforeach; ?>
                <?php if (!$quotes): ?><tr><td colspan="4" class="py-8 text-center text-slate-500">No quotes yet.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div x-show="tab === 'invoices'" x-cloak class="table-wrap"><table class="table"><thead><tr><th>Invoice</th><th>Status</th><th>Issued</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead><tbody>
                <?php foreach ($invoices as $i): ?><tr class="cursor-pointer" onclick="location.href='<?= site_url('invoices/' . $i['id']) ?>'"><td class="font-semibold"><?= esc($i['number']) ?></td><td><?= status_badge(invoice_display_status($i), 'invoice') ?></td><td><?= fdate($i['issue_date']) ?></td><td><?= fdate($i['due_date']) ?></td><td class="text-right"><?= money($i['total']) ?></td><td class="text-right font-semibold"><?= money($i['balance_due']) ?></td></tr><?php endforeach; ?>
                <?php if (!$invoices): ?><tr><td colspan="6" class="py-8 text-center text-slate-500">No invoices yet.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div x-show="tab === 'payments'" x-cloak class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Method</th><th>Reference</th><th>Date</th><th class="text-right">Amount</th></tr></thead><tbody>
                <?php foreach ($payments as $p): ?><tr><td class="font-semibold"><a class="hover:underline" target="_blank" href="<?= site_url('payments/' . $p['id'] . '/receipt') ?>"><?= esc($p['number']) ?></a></td><td><span class="badge-gray"><?= strtoupper(esc($p['method'])) ?></span></td><td><?= esc($p['reference']) ?></td><td><?= fdate($p['paid_at']) ?></td><td class="text-right font-semibold"><?= money($p['amount']) ?></td></tr><?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="5" class="py-8 text-center text-slate-500">No payments yet.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div x-show="tab === 'files'" x-cloak class="p-5"><?= view('partials/attachments', ['files' => $files, 'entity' => 'customer', 'entityId' => $c['id']]) ?></div>
            <div x-show="tab === 'activity'" x-cloak class="p-6"><?= view('partials/activity', ['activity' => $activity]) ?></div>
        </div>
        <div class="mt-4 flex justify-end">
            <form method="post" action="<?= site_url('customers/' . $c['id'] . '/delete') ?>" data-confirm="Archive this customer? Their history will be kept."><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600"><?= icon('trash', 'h-3.5 w-3.5') ?> Archive customer</button></form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
