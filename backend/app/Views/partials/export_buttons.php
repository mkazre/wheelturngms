<?php $qs = http_build_query(array_diff_key(service('request')->getGet(), ['page' => 1])); ?>
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" class="btn-light"><?= icon('download', 'h-4 w-4') ?> Export</button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 z-20 mt-2 w-48 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-navy-900">
        <a href="<?= site_url($base . '/export/xlsx') . ($qs ? '?' . $qs : '') ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5"><span class="badge-green">XLSX</span> Excel workbook</a>
        <a href="<?= site_url($base . '/export/pdf') . ($qs ? '?' . $qs : '') ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5"><span class="badge-red">PDF</span> Branded PDF</a>
    </div>
</div>
