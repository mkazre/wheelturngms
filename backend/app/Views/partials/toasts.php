<?php
$flash = [];
foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $k => $type) {
    if ($m = session()->getFlashdata($k)) {
        $flash[] = ['type' => $type, 'message' => is_array($m) ? implode(' ', $m) : $m];
    }
}
if ($errors = session()->getFlashdata('errors')) {
    $flash[] = ['type' => 'error', 'message' => implode(' ', (array) $errors)];
}
?>
<div x-data="toasts(<?= esc(json_encode($flash), 'attr') ?>)" @toast.window="push($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-auto sm:left-auto sm:right-6 sm:top-20 sm:items-end">
    <template x-for="t in items" :key="t.id">
        <div x-show="t.show" x-transition.duration.300ms
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-xl dark:bg-navy-900"
             :class="{ 'border-emerald-200 dark:border-emerald-500/30': t.type === 'success', 'border-red-200 dark:border-red-500/30': t.type === 'error', 'border-amber-200 dark:border-amber-500/30': t.type === 'warning', 'border-slate-200 dark:border-white/10': t.type === 'info' }">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white"
                  :class="{ 'bg-emerald-500': t.type === 'success', 'bg-red-500': t.type === 'error', 'bg-amber-500': t.type === 'warning', 'bg-navy-600': t.type === 'info' }">
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3"><path :d="t.type === 'success' ? 'M20 6 9 17l-5-5' : 'M12 8v5M12 16h.01'"/></svg>
            </span>
            <p class="flex-1 text-sm text-slate-700 dark:text-slate-200" x-text="t.message"></p>
            <button @click="t.show = false" class="text-slate-400 hover:text-slate-600" aria-label="Dismiss"><?= icon('x', 'h-4 w-4') ?></button>
        </div>
    </template>
</div>
