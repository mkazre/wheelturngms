<?php $token = $quote['public_token']; $open = in_array($quote['status'], ['draft', 'sent'], true); ?>
<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Quote <?= esc($quote['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="grid gap-6 lg:grid-cols-5">
    <div class="lg:col-span-3"><?= view('partials/doc_preview', ['kind' => 'quote', 'doc' => $quote, 'items' => $items, 'customer' => $customer, 'vehicle' => $vehicle]) ?></div>
    <div class="space-y-4 lg:col-span-2">
        <div class="card p-6">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Quote total (incl. VAT)</div>
            <div class="mt-1 text-4xl font-extrabold text-navy-900"><?= money($quote['total']) ?></div>
            <div class="mt-1 text-sm text-slate-500">Valid until <?= fdate($quote['expiry_date']) ?></div>
            <?php if ($open): ?>
                <form method="post" action="<?= site_url('q/' . $token . '/respond') ?>" class="mt-6 space-y-3"><?= csrf_field() ?>
                    <textarea name="comment" rows="2" class="input" placeholder="Any questions or preferred booking date? (optional)"></textarea>
                    <button name="decision" value="accept" class="btn-primary w-full py-3.5 text-base"><?= icon('check', 'h-5 w-5') ?> Accept quote</button>
                    <button name="decision" value="decline" class="btn-light w-full" onclick="return confirm('Decline this quote?')">Decline</button>
                </form>
            <?php else: ?>
                <div class="mt-5"><?= status_badge($quote['status'], 'quote') ?></div>
            <?php endif; ?>
        </div>
        <a href="<?= site_url('q/' . $token . '/pdf') ?>" target="_blank" class="btn-light w-full"><?= icon('download', 'h-4 w-4') ?> Download PDF</a>
    </div>
</div>
<?= $this->endSection() ?>
