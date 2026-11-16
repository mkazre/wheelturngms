<?php $statuses = job_statuses(); $isMech = session('role') === 'mechanic'; ?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Workshop Board<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => 'Workshop board', 'subtitle' => 'Drag vehicles between stages. Changes save instantly, and the customer is notified when a vehicle is ready.',
    'actions' => '<a href="' . site_url('workshop/tv') . '" target="_blank" class="btn-light">' . icon('eye', 'h-4 w-4') . ' TV display</a><a href="?mine=1" class="btn-light">My jobs</a><a href="' . site_url('workshop') . '" class="btn-light">' . icon('refresh', 'h-4 w-4') . ' Refresh</a>' . ($isMech ? '' : '<a href="' . site_url('job-cards/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' Check in vehicle</a>'),
]) ?>

<div x-data="board()" class="-mx-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="flex min-w-max gap-4">
        <?php foreach ($columns as $status => $jobs): $s = $statuses[$status]; ?>
            <section class="flex w-72 shrink-0 flex-col rounded-2xl bg-slate-100/70 dark:bg-white/[.03]"
                     @dragover.prevent="over = '<?= $status ?>'" @dragleave="over = null" @drop.prevent="drop('<?= $status ?>', $event)"
                     :class="over === '<?= $status ?>' && 'ring-2 ring-crimson-500'">
                <header class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold"><span class="h-2.5 w-2.5 rounded-full" style="background: <?= $s['hex'] ?>"></span><?= $s['label'] ?></div>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-300" data-count><?= count($jobs) ?></span>
                </header>
                <div class="flex min-h-[200px] flex-1 flex-col gap-3 px-3 pb-3" data-col="<?= $status ?>">
                    <?php foreach ($jobs as $j): $late = $j['promised_at'] && strtotime($j['promised_at']) < time(); ?>
                        <a href="<?= site_url('job-cards/' . $j['id']) ?>" draggable="true" data-id="<?= $j['id'] ?>" @dragstart="drag($event, <?= $j['id'] ?>)"
                           class="group block cursor-grab rounded-xl border border-slate-200/70 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-navy-900">
                            <div class="flex items-start justify-between gap-2">
                                <span class="rounded border border-slate-800 bg-amber-300 px-1.5 font-mono text-[11px] font-bold text-slate-900"><?= esc($j['registration']) ?></span>
                                <div class="flex items-center gap-1">
                                    <?php if ($j['running']): ?><span class="flex h-2 w-2 animate-pulse rounded-full bg-emerald-500" title="Technician clocked on"></span><?php endif; ?>
                                    <?php if (in_array($j['priority'], ['urgent', 'high'], true)): ?><span class="badge-red !px-1.5 !py-0 !text-[10px]"><?= ucfirst($j['priority']) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"><?= esc(trim($j['make'] . ' ' . $j['model'])) ?></div>
                            <div class="truncate text-xs text-slate-500"><?= esc(customer_name($j)) ?> · <?= esc($j['number']) ?></div>
                            <?php if ($j['customer_complaint']): ?><p class="mt-2 line-clamp-2 text-xs text-slate-600 dark:text-slate-400"><?= esc($j['customer_complaint']) ?></p><?php endif; ?>
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex -space-x-1.5">
                                    <?php foreach ($j['mechs'] as $m): ?><span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy-800 text-[9px] font-bold text-white ring-2 ring-white dark:ring-navy-900"><?= esc($m) ?></span><?php endforeach; ?>
                                </div>
                                <span class="text-[11px] font-semibold <?= $late ? 'text-red-600' : 'text-slate-400' ?>"><?= $j['promised_at'] ? ($late ? 'Late · ' : 'Due ') . date('D H:i', strtotime($j['promised_at'])) : ($j['bay'] ? esc($j['bay']) : '') ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function board() {
    return {
        over: null, dragId: null,
        drag(e, id) { this.dragId = id; e.dataTransfer.effectAllowed = 'move'; },
        async drop(status, e) {
            this.over = null;
            const card = document.querySelector(`[data-id="${this.dragId}"]`);
            const target = document.querySelector(`[data-col="${status}"]`);
            if (!card || card.parentElement === target) return;
            const from = card.parentElement;
            target.prepend(card);
            const recount = () => document.querySelectorAll('[data-col]').forEach(c => c.closest('section').querySelector('[data-count]').textContent = c.children.length);
            recount();
            try {
                const r = await wtPost(WT_BASE + 'job-cards/' + this.dragId + '/status', { status });
                toast(r.message);
            } catch (err) { from.prepend(card); recount(); toast(err.message, 'error'); }
        },
    };
}
</script>
<?= $this->endSection() ?>
