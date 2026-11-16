<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Customer Feedback<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Customer feedback (NPS)', 'subtitle' => 'Customers are asked automatically the day after collecting their vehicle. ' . fdate($from) . ' – ' . fdate($to)]) ?>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?= view('partials/kpi', ['label' => 'Net Promoter Score', 'value' => $nps === null ? '—' : $nps, 'icon' => 'zap', 'tone' => $nps === null ? 'navy' : ($nps >= 50 ? 'green' : ($nps >= 0 ? 'amber' : 'crimson')), 'hint' => '-100 to +100']) ?>
    <?= view('partials/kpi', ['label' => 'Average score', 'value' => $avg === null ? '—' : $avg . '/10', 'icon' => 'chart', 'tone' => 'navy']) ?>
    <?= view('partials/kpi', ['label' => 'Promoters · Passives · Detractors', 'value' => $promoters . ' · ' . $passives . ' · ' . $detractors, 'icon' => 'users', 'tone' => 'violet']) ?>
    <?= view('partials/kpi', ['label' => 'Response rate', 'value' => $responseRate . '%', 'icon' => 'mail', 'tone' => 'sky']) ?>
</div>
<div class="card">
    <ul class="divide-y divide-slate-100 dark:divide-white/10">
        <?php foreach ($rows as $r): ?>
            <li class="flex gap-4 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold <?= $r['score'] >= 9 ? 'bg-emerald-100 text-emerald-700' : ($r['score'] >= 7 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>"><?= (int) $r['score'] ?></span>
                <div class="flex-1"><div class="font-semibold"><?= esc(customer_name($r)) ?> <?php if ($r['job_id']): ?><a href="<?= site_url('job-cards/' . $r['job_id']) ?>" class="text-xs font-normal text-crimson-700"><?= esc($r['number']) ?></a><?php endif; ?></div>
                    <p class="text-sm text-slate-600 dark:text-slate-300"><?= $r['comment'] ? esc($r['comment']) : '<span class="text-slate-400">No comment</span>' ?></p></div>
                <span class="text-xs text-slate-400"><?= time_ago($r['updated_at']) ?></span>
            </li>
        <?php endforeach; ?>
        <?php if (!$rows): ?><li><?= view('partials/empty', ['icon' => 'users', 'title' => 'No feedback yet', 'text' => 'Surveys are sent automatically after vehicles are collected (needs the cron job and SMTP).']) ?></li><?php endif; ?>
    </ul>
</div>
<?= $this->endSection() ?>
