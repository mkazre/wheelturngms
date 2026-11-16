<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= esc($quote['number']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $actions = '<a href="' . site_url('quotes/' . $quote['id'] . '/pdf') . '" target="_blank" class="btn-light">' . icon('printer', 'h-4 w-4') . ' PDF</a>'
    . (!in_array($quote['status'], ['converted'], true) ? '<a href="' . site_url('quotes/' . $quote['id'] . '/edit') . '" class="btn-light">' . icon('edit', 'h-4 w-4') . ' Edit</a>' : ''); ?>
<?= view('partials/page_header', ['title' => 'Quote ' . esc($quote['number']) . ' ' . status_badge($quote['status'], 'quote'), 'subtitle' => esc(customer_name($customer)) . ' · ' . money($quote['total']), 'crumbs' => ['quotes' => 'Quotes'], 'actions' => $actions]) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2"><?= view('partials/doc_preview', ['kind' => 'quote', 'doc' => $quote, 'items' => $items, 'customer' => $customer, 'vehicle' => $vehicle]) ?></div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Next steps</div></div><div class="card-body space-y-3">
            <?php if ($quote['status'] !== 'converted'): ?>
                <form method="post" action="<?= site_url('quotes/' . $quote['id'] . '/convert') ?>"><?= csrf_field() ?><button class="btn-primary w-full"><?= icon('wrench', 'h-4 w-4') ?> Convert to job card</button></form>
                <form method="post" action="<?= site_url('quotes/' . $quote['id'] . '/convert') ?>" data-confirm="Create a tax invoice directly from this quote?"><?= csrf_field() ?><input type="hidden" name="to" value="invoice"><button class="btn-dark w-full"><?= icon('invoice', 'h-4 w-4') ?> Convert to invoice</button></form>
            <?php elseif ($quote['job_card_id']): ?>
                <a href="<?= site_url('job-cards/' . $quote['job_card_id']) ?>" class="btn-dark w-full">Open job card →</a>
            <?php endif; ?>
            <form method="post" action="<?= site_url('quotes/' . $quote['id'] . '/email') ?>" class="flex gap-2"><?= csrf_field() ?>
                <input name="to" type="email" value="<?= esc($customer['email']) ?>" placeholder="customer@email.com" class="input flex-1"><button class="btn-light"><?= icon('mail', 'h-4 w-4') ?> Send</button>
            </form>
            <div x-data="{ copied: false }" class="flex gap-2">
                <input readonly value="<?= site_url('q/' . $quote['public_token']) ?>" class="input flex-1 bg-slate-50 text-xs dark:bg-white/5">
                <button type="button" class="btn-light" @click="navigator.clipboard.writeText('<?= site_url('q/' . $quote['public_token']) ?>'); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy'"></span></button>
            </div>
            <?= wa_button($customer['phone'], 'Hi ' . customer_name($customer) . ', here is your quote ' . $quote['number'] . ' from ' . setting('company_name') . ' for ' . money($quote['total']) . '. View and accept online: ' . site_url('q/' . $quote['public_token']), 'Send on WhatsApp', 'w-full') ?>
            <p class="text-xs text-slate-500">The customer can view, accept or decline online.</p>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <?php foreach (['accepted' => 'Mark accepted', 'declined' => 'Mark declined'] as $s => $l): ?>
                    <form method="post" action="<?= site_url('quotes/' . $quote['id'] . '/status') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $s ?>"><button class="btn-ghost btn-sm w-full"><?= $l ?></button></form>
                <?php endforeach; ?>
            </div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Files</div></div><div class="card-body"><?= view('partials/attachments', ['files' => $files, 'entity' => 'quote', 'entityId' => $quote['id']]) ?></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Activity</div></div><div class="card-body"><?= view('partials/activity', ['activity' => $activity]) ?></div></div>
        <form method="post" action="<?= site_url('quotes/' . $quote['id'] . '/delete') ?>" data-confirm="Delete this quote?" class="text-right"><?= csrf_field() ?><button class="btn-ghost btn-sm text-red-600"><?= icon('trash', 'h-3.5 w-3.5') ?> Delete quote</button></form>
    </div>
</div>
<?= $this->endSection() ?>
