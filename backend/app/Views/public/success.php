<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Payment received<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card mx-auto max-w-lg p-10 text-center">
    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><?= icon('check', 'h-8 w-8') ?></div>
    <h1 class="text-2xl font-bold text-slate-900">Thank you!</h1>
    <p class="mt-2 text-slate-600">Your payment for invoice <b><?= esc($invoice['number']) ?></b> is being confirmed. You'll receive an emailed receipt shortly.</p>
    <p class="mt-1 text-xs text-slate-400">It can take a minute for the bank to confirm the payment.</p>
    <a href="<?= site_url('pay/' . $invoice['public_token']) ?>" class="btn-dark mt-6">View invoice</a>
</div>
<?= $this->endSection() ?>
