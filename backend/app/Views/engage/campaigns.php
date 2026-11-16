<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Campaigns<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Marketing campaigns', 'subtitle' => 'Email or SMS targeted customer groups. Only customers who opted in receive promotions (POPIA).',
    'actions' => '<form method="post" action="' . site_url('campaigns/statements') . '" onsubmit="return confirm(\'Email statements to every customer with a balance?\')">' . csrf_field() . '<button class="btn-light">' . icon('file', 'h-4 w-4') . ' Email all statements</button></form>']) ?>
<div class="grid gap-6 xl:grid-cols-3">
    <form method="post" action="<?= site_url('campaigns/send') ?>" class="card xl:col-span-2" x-data="{ ch: 'email', seg: 'service_due', body: '' }" onsubmit="return confirm('Send this campaign now?')"><?= csrf_field() ?>
        <div class="card-header"><div class="card-title">New campaign</div></div>
        <div class="card-body space-y-4">
            <div><label class="label">Audience</label>
                <div class="grid gap-2 sm:grid-cols-2">
                    <?php foreach ($segments as $k => $l): ?>
                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-xl border p-3 text-sm" :class="seg === '<?= $k ?>' ? 'border-crimson-500 bg-crimson-50/60 dark:bg-crimson-500/10' : 'border-slate-200 dark:border-white/10'">
                            <span class="flex items-center gap-2"><input type="radio" name="segment" value="<?= $k ?>" x-model="seg" class="text-crimson-600"> <?= $l ?></span><span class="badge-gray"><?= $counts[$k] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-2">
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="channel" value="email" x-model="ch" class="text-crimson-600"> Email</label>
                <label class="flex items-center gap-2 text-sm <?= $smsOn ? '' : 'opacity-50' ?>"><input type="radio" name="channel" value="sms" x-model="ch" <?= $smsOn ? '' : 'disabled' ?> class="text-crimson-600"> SMS <?= $smsOn ? '' : '(set up in Settings → SMS)' ?></label>
            </div>
            <div><label class="label">Campaign name</label><input name="name" class="input" placeholder="e.g. Winter service special"></div>
            <div x-show="ch === 'email'"><label class="label">Email subject</label><input name="subject" class="input" placeholder="{first_name}, your winter service special is here"></div>
            <div><label class="label">Message</label><textarea name="body" x-model="body" rows="7" class="input" placeholder="Hi {first_name},&#10;&#10;Book your winter service before 30 June and get a free battery & brake check. {book_link}"></textarea>
                <p class="mt-1 flex justify-between text-xs text-slate-500"><span>Placeholders: {first_name} {name} {company} {book_link}</span><span x-show="ch === 'sms'" x-text="body.length + ' chars · ' + Math.max(1, Math.ceil(body.length / 153)) + ' SMS'"></span></p></div>
            <button class="btn-primary"><?= icon('mail', 'h-4 w-4') ?> Send campaign</button>
        </div>
    </form>
    <div class="card"><div class="card-header"><div class="card-title">Sent campaigns</div></div>
        <ul class="divide-y divide-slate-100 text-sm dark:divide-white/10">
            <?php foreach ($rows as $r): ?><li class="px-5 py-3"><div class="flex justify-between"><b><?= esc($r['name']) ?></b><span class="badge-gray"><?= strtoupper(esc($r['channel'])) ?></span></div><div class="text-xs text-slate-500"><?= esc($segments[$r['segment']] ?? $r['segment']) ?> · <?= (int) $r['sent_count'] ?>/<?= (int) $r['recipients'] ?> sent · <?= fdate($r['sent_at']) ?></div></li><?php endforeach; ?>
            <?php if (!$rows): ?><li class="px-5 py-8 text-center text-slate-500">No campaigns yet.</li><?php endif; ?>
        </ul>
    </div>
</div>
<?= $this->endSection() ?>
