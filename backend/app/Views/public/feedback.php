<?php $thanks = service('request')->getGet('thanks'); $review = setting('google_review_url'); ?>
<?= $this->extend('layouts/public') ?>
<?= $this->section('title') ?>Your feedback<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="card mx-auto max-w-xl p-8">
<?php if ($thanks || $f['submitted_at']): ?>
    <div class="text-center">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><?= icon('check', 'h-8 w-8') ?></div>
        <h1 class="text-2xl font-bold">Thank you for your feedback</h1>
        <?php if ((int) $f['score'] >= 9 && $review): ?>
            <p class="mt-2 text-slate-600">We're thrilled you had a great experience. Would you mind sharing it on Google? It really helps a local business like ours.</p>
            <a href="<?= esc($review, 'attr') ?>" target="_blank" rel="noopener" class="btn-primary mt-6">Leave a Google review</a>
        <?php else: ?>
            <p class="mt-2 text-slate-600">Every response is read by our management team. If you asked us to get in touch, we will.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <h1 class="text-xl font-bold text-navy-900">How likely are you to recommend <?= esc(setting('company_name')) ?> to a friend?</h1>
    <form method="post" action="<?= site_url('feedback/' . $f['token']) ?>" class="mt-6 space-y-5" x-data="{ s: <?= $f['score'] === null ? 'null' : (int) $f['score'] ?> }"><?= csrf_field() ?>
        <div class="grid grid-cols-11 gap-1">
            <?php for ($i = 0; $i <= 10; $i++): ?>
                <button type="button" @click="s = <?= $i ?>" :class="s === <?= $i ?> ? 'bg-navy-900 text-white' : '<?= $i <= 6 ? 'bg-red-50 text-red-700' : ($i <= 8 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') ?>'" class="rounded-lg py-2.5 text-sm font-bold"><?= $i ?></button>
            <?php endfor; ?>
        </div>
        <div class="flex justify-between text-xs text-slate-400"><span>Not likely</span><span>Extremely likely</span></div>
        <input type="hidden" name="score" :value="s">
        <div><label class="label">What's the main reason for your score?</label><textarea name="comment" rows="4" class="input"></textarea></div>
        <button class="btn-primary w-full py-3" :disabled="s === null">Send feedback</button>
    </form>
<?php endif; ?>
</div>
<?= $this->endSection() ?>
