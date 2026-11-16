<?php $open = in_array($invoice['status'], ['unpaid', 'partial'], true) && (float) $invoice['balance_due'] > 0; $token = $invoice['public_token']; ?>
<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Invoice <?= esc($invoice['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($cancelled): ?><div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">Payment was cancelled. You can try again below.</div><?php endif; ?>
<?php if ($error): ?><div class="mb-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">The payment could not be completed. Please try again or use another method.</div><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-5">
    <div class="lg:col-span-3"><?= view('partials/doc_preview', ['kind' => 'invoice', 'doc' => $invoice, 'items' => $items, 'customer' => $customer, 'vehicle' => $vehicle, 'job' => $job]) ?></div>
    <div class="space-y-4 lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="bg-gradient-to-br from-navy-900 to-navy-700 p-6 text-white">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-300"><?= $open ? 'Amount due' : 'Invoice total' ?></div>
                <div class="mt-1 text-4xl font-extrabold"><?= money($open ? $invoice['balance_due'] : $invoice['total']) ?></div>
                <div class="mt-2 text-sm text-slate-300"><?= esc($invoice['number']) ?> · <?= $open ? 'Due ' . fdate($invoice['due_date']) : '' ?></div>
                <?php if (!$open && $invoice['status'] === 'paid'): ?><div class="mt-3 inline-flex items-center gap-2 rounded-full bg-emerald-500 px-3 py-1 text-sm font-bold"><?= icon('check', 'h-4 w-4') ?> Paid in full. Thank you!</div><?php endif; ?>
            </div>
            <?php if ($open): ?>
            <div class="space-y-3 p-5">
                <?php if ($payfast): ?>
                    <form method="post" action="<?= site_url('pay/' . $token . '/payfast') ?>"><?= csrf_field() ?>
                        <button class="btn-primary w-full py-3.5 text-base"><?= icon('card', 'h-5 w-5') ?> Pay with card / PayFast</button>
                        <p class="mt-1.5 text-center text-[11px] text-slate-500">Visa · Mastercard · Instant EFT · SnapScan · Zapper · Mobicred</p>
                    </form>
                <?php endif; ?>
                <?php if ($ozow): ?>
                    <form method="post" action="<?= site_url('pay/' . $token . '/ozow') ?>"><?= csrf_field() ?>
                        <button class="btn w-full bg-[#1e1e5a] py-3.5 text-base text-white hover:brightness-110"><?= icon('zap', 'h-5 w-5') ?> Pay by Instant EFT (Ozow)</button>
                        <p class="mt-1.5 text-center text-[11px] text-slate-500">Pay straight from your banking app. No card needed.</p>
                    </form>
                <?php endif; ?>
                <?php if (setting('bank_account_number')): ?>
                    <div class="rounded-2xl border border-slate-200 p-4 text-sm">
                        <div class="mb-1 font-bold">Or pay by EFT</div>
                        <dl class="grid grid-cols-2 gap-1 text-xs"><dt class="text-slate-500">Bank</dt><dd><?= esc(setting('bank_name')) ?></dd><dt class="text-slate-500">Account name</dt><dd><?= esc(setting('bank_account_name')) ?></dd><dt class="text-slate-500">Account no.</dt><dd class="font-mono font-semibold"><?= esc(setting('bank_account_number')) ?></dd><dt class="text-slate-500">Branch code</dt><dd class="font-mono"><?= esc(setting('bank_branch_code')) ?></dd><dt class="text-slate-500">Reference</dt><dd class="font-mono font-bold text-crimson-700"><?= esc($invoice['number']) ?></dd></dl>
                    </div>
                <?php endif; ?>
                <div class="flex items-center justify-center gap-2 pt-1 text-[11px] text-slate-400"><?= icon('shield', 'h-3.5 w-3.5') ?> 256-bit SSL · We never see or store your card details</div>
            </div>
            <?php endif; ?>
        </div>
        <a href="<?= site_url('pay/' . $token . '/pdf') ?>" target="_blank" class="btn-light w-full"><?= icon('download', 'h-4 w-4') ?> Download PDF</a>
    </div>
</div>
<?= $this->endSection() ?>
