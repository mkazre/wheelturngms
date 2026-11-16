<div x-data="wtScanner()" @scan.window="start($event.detail)">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/80 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-navy-900">
            <div class="flex items-center justify-between px-5 py-4"><span class="font-bold">Scan barcode</span><button type="button" @click="stop()" class="btn-ghost p-1"><?= icon('x', 'h-5 w-5') ?></button></div>
            <div class="relative aspect-[4/3] bg-black">
                <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
                <div class="pointer-events-none absolute inset-x-10 top-1/2 h-0.5 -translate-y-1/2 animate-pulse bg-brand-red shadow-[0_0_12px_#ef1c25]"></div>
            </div>
            <p x-show="error" x-text="error" class="px-5 py-3 text-sm text-red-600"></p>
            <p x-show="!error" class="px-5 py-3 text-xs text-slate-500">Point the camera at the barcode. Hold steady.</p>
        </div>
    </div>
</div>
