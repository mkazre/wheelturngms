<?php
use App\Controllers\Assistant;

$jobRow = $job['job'] ?? null;
$veh = $job['vehicle'] ?? null;
$vehDesc = $veh ? trim($veh['year'] . ' ' . $veh['make'] . ' ' . $veh['model'] . ' ' . $veh['engine_size']) : '';
$initial = array_map(static fn ($m) => ['role' => $m['role'], 'html' => $m['role'] === 'assistant' ? Assistant::render($m['content']) : nl2br(esc($m['content']))], $messages);
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>AI Assistant<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'AI workshop assistant', 'subtitle' => 'Diagnosis help, repair procedures and fault codes, powered by Claude. Always verify against manufacturer data.']) ?>
<?php if (!$enabled): ?>
    <div class="card p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-crimson-50 text-crimson-700"><?= icon('zap', 'h-7 w-7') ?></div>
        <h2 class="text-lg font-bold">The AI assistant is not switched on yet</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">An administrator needs to add an Anthropic API key in Settings → AI assistant. Only vehicle and job details are sent, never customer names or contact details.</p>
        <?php if (can('settings')): ?><a href="<?= site_url('settings/ai') ?>" class="btn-primary mt-5">Set up the AI assistant</a><?php endif; ?>
    </div>
<?php else: ?>
<div class="grid gap-6 xl:grid-cols-4">
    <div class="space-y-4">
        <a href="<?= site_url('assistant' . ($jobRow ? '?job=' . $jobRow['id'] : '')) ?>" class="btn-primary w-full"><?= icon('plus', 'h-4 w-4') ?> New conversation</a>
        <div class="card"><div class="card-header"><div class="card-title">Recent</div></div>
            <ul class="divide-y divide-slate-100 text-sm dark:divide-white/10">
                <?php foreach ($convs as $c): ?><li><a href="<?= site_url('assistant?c=' . $c['id']) ?>" class="block truncate px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-white/5 <?= ($current['id'] ?? 0) == $c['id'] ? 'font-semibold text-crimson-700' : '' ?>"><?= esc($c['title']) ?></a></li><?php endforeach; ?>
                <?php if (!$convs): ?><li class="px-4 py-4 text-slate-500">No conversations yet.</li><?php endif; ?>
            </ul>
        </div>
        <div class="card" x-data="{ codes: '', vehicle: '<?= esc($vehDesc, 'js') ?>', busy: false, html: '', async go() { this.busy = true; this.html = ''; try { const r = await wtPost(WT_BASE + 'assistant/fault-codes', { codes: this.codes, vehicle: this.vehicle }); this.html = r.html; } catch (e) { toast(e.message, 'error'); } this.busy = false; } }">
            <div class="card-header"><div class="card-title">Fault code explainer</div></div>
            <div class="card-body space-y-2">
                <input x-model="codes" placeholder="P0301, P0171" class="input font-mono uppercase" data-no-voice>
                <input x-model="vehicle" placeholder="Vehicle (e.g. 2018 VW Polo 1.0 TSI)" class="input text-sm">
                <button @click="go()" :disabled="busy || !codes" class="btn-dark w-full"><span x-show="!busy">Explain codes</span><span x-show="busy" x-cloak>Thinking…</span></button>
                <div x-show="html" x-cloak class="prose-sm max-h-96 overflow-y-auto rounded-xl bg-slate-50 p-3 text-sm leading-relaxed dark:bg-white/5" x-html="html"></div>
            </div>
        </div>
    </div>

    <div class="card flex h-[calc(100vh-14rem)] min-h-[520px] flex-col xl:col-span-3"
         x-data="{ msgs: <?= esc(json_encode($initial), 'attr') ?>, q: '', busy: false, conv: <?= (int) ($current['id'] ?? 0) ?>,
            async send(text) { const m = (text || this.q).trim(); if (!m || this.busy) return; this.msgs.push({ role: 'user', html: m.replace(/</g, '&lt;').replace(/\n/g, '<br>') }); this.q = ''; this.busy = true; this.scroll();
                try { const r = await wtPost(WT_BASE + 'assistant/ask', { message: m, conversation_id: this.conv, job_card_id: '<?= (int) ($jobRow['id'] ?? 0) ?: '' ?>' }); this.conv = r.conversation_id; this.msgs.push({ role: 'assistant', html: r.html }); }
                catch (e) { this.msgs.push({ role: 'error', html: e.message }); } this.busy = false; this.scroll(); },
            scroll() { this.$nextTick(() => { this.$refs.log.scrollTop = this.$refs.log.scrollHeight; }); } }" x-init="scroll()">
        <?php if ($jobRow): ?>
            <div class="flex items-center gap-3 border-b border-slate-100 bg-navy-50/60 px-5 py-3 text-sm dark:border-white/10 dark:bg-white/5">
                <?= icon('wrench', 'h-4 w-4 text-crimson-600') ?><span>Using job <a href="<?= site_url('job-cards/' . $jobRow['id']) ?>" class="font-semibold text-crimson-700"><?= esc($jobRow['number']) ?></a> · <?= esc($vehDesc) ?> as context</span>
            </div>
        <?php endif; ?>
        <div x-ref="log" class="flex-1 space-y-4 overflow-y-auto p-5">
            <template x-if="!msgs.length">
                <div class="mx-auto max-w-lg py-10 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-crimson-700 to-brand-red text-white shadow-glow"><?= icon('zap', 'h-7 w-7') ?></div>
                    <h3 class="text-lg font-bold">How can I help with this repair?</h3>
                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <?php foreach ($jobRow ? ['What are the most likely causes of this complaint?', 'Give me a step-by-step diagnostic plan', 'What parts should I quote for this job?', 'Explain the inspection flags in simple terms'] : ['Hilux 2.8 GD-6 losing power under load, what to check?', 'Polo Vivo idle hunting after a battery change', 'Ranger 2.2 clutch replacement steps', 'How do I bleed brakes on a car with ABS?'] as $s): ?>
                            <button type="button" @click="send('<?= esc($s, 'js') ?>')" class="rounded-xl border border-slate-200 p-3 text-left text-sm hover:border-crimson-300 hover:bg-crimson-50/40 dark:border-white/10 dark:hover:bg-white/5"><?= esc($s) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </template>
            <template x-for="(m, i) in msgs" :key="i">
                <div class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed"
                         :class="m.role === 'user' ? 'bg-navy-900 text-white' : (m.role === 'error' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-800 dark:bg-white/5 dark:text-slate-200')" x-html="m.html"></div>
                </div>
            </template>
            <div x-show="busy" x-cloak class="flex"><div class="rounded-2xl bg-slate-100 px-4 py-3 dark:bg-white/5"><span class="inline-flex gap-1"><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400"></span><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:.15s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:.3s]"></span></span></div></div>
        </div>
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-slate-100 p-4 dark:border-white/10">
            <textarea x-model="q" @keydown.enter.prevent="if (!$event.shiftKey) send(); else q += '\n'" rows="2" placeholder="Describe the symptom, ask for a procedure, or paste fault codes…" class="input flex-1 resize-none"></textarea>
            <button class="btn-primary h-11" :disabled="busy"><?= icon('arrow-right', 'h-4 w-4') ?></button>
        </form>
        <p class="px-4 pb-3 text-[11px] text-slate-400">AI suggestions can be wrong. Confirm torque values, specifications and procedures in manufacturer data before working on the vehicle.</p>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
