<?php use App\Libraries\Attachments as Files; /** @var array $files @var string $entity @var int $entityId */ ?>
<div x-data="uploader('<?= site_url('attachments/upload') ?>', '<?= esc($entity) ?>', <?= (int) $entityId ?>)" x-init="category = '<?= esc($defaultCategory ?? 'document') ?>'">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <label class="text-xs font-semibold text-slate-500">Upload as</label>
        <select x-model="category" class="input w-auto py-1.5 text-xs">
            <?php foreach (Files::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
        </select>
    </div>
    <label @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; send($event.dataTransfer.files)"
           :class="dragging ? 'border-crimson-500 bg-crimson-50/60 dark:bg-crimson-500/10' : 'border-slate-300 dark:border-white/15'"
           class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 text-center transition hover:border-crimson-400">
        <input type="file" multiple class="sr-only" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" @change="send($event.target.files)">
        <span class="mb-2 text-crimson-600"><?= icon('upload', 'h-7 w-7') ?></span>
        <span class="text-sm font-semibold" x-show="!uploading">Drop files or tap to upload</span>
        <span class="text-sm font-semibold" x-show="uploading" x-cloak>Uploading…</span>
        <span class="mt-1 text-xs text-slate-500">Photos, walk-around videos (60 MB), slips, PDFs (15 MB)</span>
    </label>
    <?php if ($files): ?>
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($files as $f): ?>
                <li class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
                    <a href="<?= site_url('attachments/' . $f['id']) ?>" target="_blank" class="block aspect-[4/3] bg-slate-50 dark:bg-white/5">
                        <?php if (str_starts_with((string) $f['mime'], 'video/')): ?>
                            <video src="<?= site_url('attachments/' . $f['id']) ?>" preload="metadata" muted class="h-full w-full object-cover"></video>
                        <?php elseif (Files::isImage($f)): ?>
                            <img src="<?= site_url('attachments/' . $f['id']) ?>" alt="<?= esc($f['original_name']) ?>" loading="lazy" class="h-full w-full object-cover">
                        <?php else: ?>
                            <div class="flex h-full flex-col items-center justify-center text-slate-400"><?= icon('file', 'h-10 w-10') ?><span class="mt-1 text-[10px] font-bold uppercase"><?= esc(pathinfo($f['original_name'], PATHINFO_EXTENSION)) ?></span></div>
                        <?php endif; ?>
                    </a>
                    <div class="p-2">
                        <div class="truncate text-xs font-semibold" title="<?= esc($f['original_name']) ?>"><?= esc($f['original_name']) ?></div>
                        <div class="mt-0.5 flex items-center justify-between text-[10px] text-slate-500">
                            <span class="badge-gray !px-1.5 !py-0"><?= esc(Files::CATEGORIES[$f['category']] ?? $f['category']) ?></span>
                            <span><?= number_to_size($f['size']) ?></span>
                        </div>
                    </div>
                    <div class="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition group-hover:opacity-100">
                        <a href="<?= site_url('attachments/' . $f['id'] . '/download') ?>" class="rounded-lg bg-white/90 p-1.5 text-slate-700 shadow" title="Download"><?= icon('download', 'h-3.5 w-3.5') ?></a>
                        <form method="post" action="<?= site_url('attachments/' . $f['id'] . '/delete') ?>" data-confirm="Delete this file?"><?= csrf_field() ?><button class="rounded-lg bg-white/90 p-1.5 text-red-600 shadow" title="Delete"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
