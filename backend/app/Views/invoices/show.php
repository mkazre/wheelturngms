<?php $ds = invoice_display_status($invoice); $payLink = site_url('pay/' . $invoice['public_token']); $open = in_array($invoice['status'], ['unpaid', 'partial'], true); ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($invoice['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $actions = '<a href="' . site_url('invoices/' . $invoice['id'] . '/pdf') . '" target="_blank" class="btn-light">' . icon('printer', 'h-4 w-4') . ' PDF</a>'
    . ((float) $invoice['amount_paid'] == 0 && (float) $invoice['amount_credited'] == 0 && $invoice['status'] !== 'void' ? '<a href="' . site_url('invoices/' . $invoice['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>' : '')
    . (!in_array($invoice['status'], ['draft', 'void'], true) && can('invoices') ? '<a href="' . site_url('credit-notes/new?invoice_id=' . $invoice['id']) . '" class="btn-light">' . icon('receipt', 'h-4 w-4') . ' Credit note</a>' : ''); ?>
<?= view('partials/page_header', ['title' => esc($invoice['number']) . ' ' . status_badge($ds, 'invoice'), 'subtitle' => esc(customer_name($customer)) . ($invoice['job_card_id'] && $job ? ' · from job card <a class="font-semibold text-crimson-700 dark:text-crimson-400" href="' . site_url('job-cards/' . $job['id']) . '">' . esc($job['number']) . '</a>' : ''), 'crumbs' => ['invoices' => 'Invoices'], 'actions' => $actions]) ?>

<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <?= view('partials/doc_preview', ['kind' => 'invoice', 'doc' => $invoice, 'items' => $items, 'customer' => $customer, 'vehicle' => $vehicle, 'job' => $job]) ?>

        <?php if ($credits): ?>
        <div class="card"><div class="card-header"><div class="card-title">Credit notes</div><span class="text-sm font-semibold">-<?= money($invoice['amount_credited']) ?></span></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10"><?php foreach ($credits as $cr): ?><li><a href="<?= site_url('credit-notes/' . $cr['id']) ?>" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-white/5"><span><b><?= esc($cr['number']) ?></b> · <?= fdate($cr['issue_date']) ?> · <span class="text-slate-500"><?= esc($cr['reason']) ?></span></span><span class="font-semibold <?= $cr['status'] === 'void' ? 'line-through text-slate-400' : '' ?>">-<?= money($cr['total']) ?></span></a></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <div class="card"><div class="card-header"><div class="card-title">Payments</div><span class="text-sm font-semibold"><?= money($invoice['amount_paid']) ?> received</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr><td class="font-semibold"><?= esc($p['number']) ?></td><td><?= fdatetime($p['paid_at']) ?></td><td><span class="badge-gray"><?= strtoupper(esc($p['method'])) ?></span></td><td class="text-slate-500"><?= esc($p['reference'] ?: $p['gateway_txn_id']) ?></td><td class="text-right font-semibold"><?= money($p['amount']) ?></td>
                        <td class="text-right"><a href="<?= site_url('payments/' . $p['id'] . '/receipt') ?>" target="_blank" class="btn-ghost btn-sm">Receipt</a></td></tr>
                <?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="6" class="py-6 text-center text-slate-500">No payments yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php if ($slips): ?>
                <div class="border-t border-slate-100 p-5 dark:border-white/10"><div class="label">Proof of payment / slips</div>
                    <div class="flex flex-wrap gap-2"><?php foreach ($slips as $s): ?><a href="<?= site_url('attachments/' . $s['id']) ?>" target="_blank" class="badge-blue"><?= icon('paperclip', 'h-3 w-3') ?> <?= esc($s['original_name']) ?></a><?php endforeach; ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <?php if ($open): ?>
        <div class="card overflow-hidden">
            <div class="bg-gradient-to-br from-crimson-700 to-brand-red p-5 text-white">
                <div class="text-xs font-semibold uppercase tracking-wider text-white/80">Balance due</div>
                <div class="mt-1 text-3xl font-extrabold"><?= money($invoice['balance_due']) ?></div>
                <div class="mt-1 text-xs text-white/80"><?= $ds === 'overdue' ? 'Overdue since ' : 'Due ' ?><?= fdate($invoice['due_date']) ?></div>
            </div>
            <form method="post" enctype="multipart/form-data" action="<?= site_url('invoices/' . $invoice['id'] . '/payment') ?>" class="card-body space-y-3" x-data="{ amt: <?= (float) $invoice['balance_due'] ?> }">
                <?= csrf_field() ?>
                <div class="card-title">Record a payment</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Amount</label><input name="amount" type="number" step="0.01" min="0.01" max="<?= (float) $invoice['balance_due'] ?>" x-model="amt" class="input" required></div>
                    <div><label class="label">Method</label><select name="method" class="input"><?php foreach ($methods as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Date</label><input name="paid_at" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>" class="input"></div>
                    <div><label class="label">Reference</label><input name="reference" class="input" placeholder="Bank ref / slip no."></div>
                </div>
                <div><label class="label">Proof of payment (slip)</label><input type="file" name="slip" accept="image/*,.pdf" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white"></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_receipt" value="1" checked class="rounded text-crimson-600 focus:ring-crimson-500"> Email receipt to customer</label>
                <button class="btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> Record payment</button>
            </form>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Get paid faster</div></div><div class="card-body space-y-3">
            <form method="post" action="<?= site_url('invoices/' . $invoice['id'] . '/email') ?>" class="flex gap-2"><?= csrf_field() ?>
                <input name="to" type="email" value="<?= esc($customer['email']) ?>" placeholder="customer@email.com" class="input flex-1"><button class="btn-dark"><?= icon('mail', 'h-4 w-4') ?> Send</button>
            </form>
            <form method="post" action="<?= site_url('invoices/' . $invoice['id'] . '/remind') ?>"><?= csrf_field() ?><button class="btn-light w-full"><?= icon('bell', 'h-4 w-4') ?> Send payment reminder</button></form>
            <div x-data="{ copied: false }" class="flex gap-2">
                <input readonly value="<?= $payLink ?>" class="input flex-1 bg-slate-50 text-xs dark:bg-white/5">
                <button type="button" class="btn-light" @click="navigator.clipboard.writeText('<?= $payLink ?>'); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy'"></span></button>
            </div>
            <?php if ($customer['phone']): $wa = preg_replace('/\D/', '', $customer['phone']); $wa = str_starts_with($wa, '0') ? '27' . substr($wa, 1) : $wa; ?>
                <a target="_blank" rel="noopener" href="https://wa.me/<?= $wa ?>?text=<?= rawurlencode('Hi ' . customer_name($customer) . ', your invoice ' . $invoice['number'] . ' from ' . setting('company_name') . ' for ' . money($invoice['balance_due']) . ' is ready. View and pay securely online: ' . $payLink) ?>" class="btn w-full bg-[#25D366] text-white hover:brightness-95">Share via WhatsApp</a>
            <?php endif; ?>
            <p class="text-xs text-slate-500">The pay link lets the customer pay instantly with <?= (int) setting('payfast_enabled') ? 'PayFast (card, Instant EFT, SnapScan…)' : '' ?><?= (int) setting('payfast_enabled') && (int) setting('ozow_enabled') ? ' or ' : '' ?><?= (int) setting('ozow_enabled') ? 'Ozow Instant EFT' : '' ?><?= !(int) setting('payfast_enabled') && !(int) setting('ozow_enabled') ? 'EFT. Enable PayFast or Ozow in Settings → Payments' : '' ?>.</p>
        </div></div>
        <?php endif; ?>

        <?php if ($emails): ?>
        <div class="card"><div class="card-header"><div class="card-title">Email log</div></div>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-white/10">
                <?php foreach ($emails as $e): ?><li class="flex items-center justify-between gap-2 px-5 py-2.5"><span class="truncate"><?= esc($e['recipient']) ?></span><span class="<?= $e['status'] === 'sent' ? 'badge-green' : 'badge-red' ?>" title="<?= esc($e['error']) ?>"><?= $e['status'] ?></span><span class="text-xs text-slate-400"><?= time_ago($e['created_at']) ?></span></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card"><div class="card-header"><div class="card-title">Files</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'invoice', 'entityId' => $invoice['id']]) ?></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Activity</div></div><div class="card-body"><?= view('partials/activity', ['activity' => $activity]) ?></div></div>

        <?php if ($invoice['status'] !== 'void' && (float) $invoice['amount_paid'] == 0): ?>
            <form method="post" action="<?= site_url('invoices/' . $invoice['id'] . '/void') ?>" data-confirm="Void this invoice? Stock will be returned and the ledger reversed. This cannot be undone." class="flex gap-2"><?= csrf_field() ?>
                <input name="reason" placeholder="Reason for voiding" class="input flex-1 text-xs"><button class="btn-ghost btn-sm text-red-600"><?= icon('x', 'h-3.5 w-3.5') ?> Void</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
