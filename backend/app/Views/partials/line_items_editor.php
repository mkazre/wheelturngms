<?php

use App\Libraries\LineItems;

/** @var array $lines @var array $mechanics (optional) */
$mechanics = $mechanics ?? [];
$accounts = $accounts ?? [];
$mode = $mode ?? 'sale';
$opts = [
    'vatOn' => (int) setting('vat_registered', 1) === 1,
    'vatRate' => (float) setting('vat_rate', 15),
    'labourRate' => (float) setting('default_labour_rate', 0),
    'types' => LineItems::TYPES,
    'mode' => $mode,
];
$packages = $mode === 'sale' ? db_connect()->table('canned_jobs')->select('id, name, labour_hours')->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get()->getResultArray() : [];
?>
<div x-data="lineItems(<?= esc(json_encode(array_values($lines)), 'attr') ?>, <?= esc(json_encode($opts), 'attr') ?>)" class="card">
    <div class="card-header">
        <div class="card-title">Labour, parts &amp; sundries</div>
        <div class="flex flex-wrap gap-1.5">
            <?php if ($packages): ?>
                <select @change="loadPackage($event.target.value); $event.target.value = ''" class="input w-auto py-1 text-xs" title="Insert a service package (labour + parts)">
                    <option value="">+ Service package…</option>
                    <?php foreach ($packages as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?><?= (float) $p['labour_hours'] ? ' (' . (float) $p['labour_hours'] . 'h)' : '' ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="button" @click="add('labour')" class="btn-light btn-sm"><?= icon('wrench', 'h-3.5 w-3.5') ?> Labour</button>
            <button type="button" @click="add('part')" class="btn-light btn-sm"><?= icon('box', 'h-3.5 w-3.5') ?> Part</button>
            <button type="button" @click="add('sundry')" class="btn-light btn-sm"><?= icon('plus', 'h-3.5 w-3.5') ?> Other</button>
        </div>
    </div>

    <div class="divide-y divide-slate-100 dark:divide-white/10">
        <template x-for="(l, i) in lines" :key="l.key">
            <div class="grid grid-cols-12 gap-2 px-4 py-3 sm:px-5">
                <input type="hidden" :name="`items[${i}][product_id]`" :value="l.product_id">
                <input type="hidden" :name="`items[${i}][unit_cost]`" :value="l.unit_cost">
                <input type="hidden" :name="`items[${i}][warranty_months]`" :value="l.warranty_months">
                <div class="col-span-4 sm:col-span-2">
                    <label class="label sm:hidden">Type</label>
                    <select :name="`items[${i}][item_type]`" x-model="l.item_type" class="input px-2 py-2 text-xs">
                        <template x-for="(label, key) in types" :key="key"><option :value="key" x-text="label" :selected="key === l.item_type"></option></template>
                    </select>
                </div>
                <div class="relative col-span-8 sm:col-span-4" @click.outside="l.results = []">
                    <label class="label sm:hidden">Description</label>
                    <input data-desc :name="`items[${i}][description]`" x-model="l.description" @input.debounce.250ms="search(l, l.description)" placeholder="Search parts or type a description…" autocomplete="off" class="input py-2">
                    <div x-show="l.results && l.results.length" x-cloak class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl dark:border-white/10 dark:bg-navy-900">
                        <template x-for="p in l.results" :key="p.id">
                            <button type="button" @click="pick(l, p)" class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5">
                                <span><span class="font-semibold" x-text="p.name"></span> <span class="text-xs text-slate-500" x-text="p.sku"></span></span>
                                <span class="text-right text-xs"><span class="font-semibold" x-text="money(p.sell_price)"></span><br><span class="text-slate-500" x-show="p.track_stock == 1" x-text="Number(p.qty_on_hand) + ' in stock'"></span></span>
                            </button>
                        </template>
                    </div>
                    <?php if ($accounts): ?>
                    <select :name="`items[${i}][account_id]`" x-model="l.account_id" class="input mt-1.5 py-1 text-xs" title="Ledger account (leave blank for stock / cost of sales)">
                        <option value="">Auto: stock or cost of sales</option>
                        <?php foreach ($accounts as $ac): ?><option value="<?= $ac['id'] ?>"><?= esc($ac['code'] . ' ' . $ac['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <?php if ($mechanics): ?>
                    <select :name="`items[${i}][mechanic_id]`" x-model="l.mechanic_id" x-show="l.item_type === 'labour'" class="input mt-1.5 py-1 text-xs">
                        <option value="">— Technician —</option>
                        <?php foreach ($mechanics as $m): ?><option value="<?= $m['id'] ?>"><?= esc($m['first_name'] . ' ' . $m['last_name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-span-3 sm:col-span-1">
                    <label class="label sm:hidden" x-text="l.item_type === 'labour' ? 'Hours' : 'Qty'"></label>
                    <input type="number" step="0.01" min="0" :name="`items[${i}][quantity]`" x-model.number="l.quantity" class="input px-2 py-2 text-right">
                </div>
                <div class="col-span-4 sm:col-span-2">
                    <label class="label sm:hidden"><?= $mode === 'purchase' ? 'Unit cost (excl.)' : 'Unit price (excl.)' ?></label>
                    <input type="number" step="0.01" :name="`items[${i}][unit_price]`" x-model.number="l.unit_price" class="input px-2 py-2 text-right">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="label sm:hidden">Disc %</label>
                    <input type="number" step="0.01" min="0" max="100" :name="`items[${i}][discount_percent]`" x-model.number="l.discount_percent" class="input px-2 py-2 text-right" title="Discount %">
                </div>
                <input type="hidden" :name="`items[${i}][vat_rate]`" :value="l.vat_rate">
                <div class="col-span-3 flex items-center justify-end gap-1 sm:col-span-2">
                    <div class="mr-1 text-right"><div class="text-sm font-semibold" x-text="money(net(l))"></div><div class="text-[10px] text-slate-500" x-show="vatOn" x-text="'+ VAT ' + money(vat(l))"></div></div>
                    <div class="flex flex-col">
                        <button type="button" @click="move(i, -1)" class="rounded p-0.5 text-slate-400 hover:text-slate-700" title="Move up">▲</button>
                        <button type="button" @click="move(i, 1)" class="rounded p-0.5 text-slate-400 hover:text-slate-700" title="Move down">▼</button>
                    </div>
                    <button type="button" @click="remove(i)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Remove"><?= icon('x', 'h-4 w-4') ?></button>
                </div>
            </div>
        </template>
        <div x-show="!lines.length" class="px-5 py-10 text-center text-sm text-slate-500">No lines yet. Add labour or parts above.</div>
    </div>

    <div class="flex flex-col gap-4 border-t border-slate-100 bg-slate-50/60 p-5 sm:flex-row sm:items-end sm:justify-between dark:border-white/10 dark:bg-white/5">
        <div class="text-xs text-slate-500">
            <div x-show="!purchase">Estimated gross profit: <span class="font-semibold" :class="profit >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="money(profit)"></span></div>
            <div x-show="vatOn">Prices exclude VAT · VAT @ <span x-text="vatRate"></span>%</div>
        </div>
        <dl class="w-full max-w-xs space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd x-text="money(subtotal)"></dd></div>
            <div class="flex justify-between" x-show="discount > 0"><dt class="text-slate-500">Discount</dt><dd class="text-red-600" x-text="'- ' + money(discount)"></dd></div>
            <div class="flex justify-between" x-show="vatOn"><dt class="text-slate-500">VAT</dt><dd x-text="money(vatTotal)"></dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold dark:border-white/10"><dt>Total</dt><dd x-text="money(grand)"></dd></div>
        </dl>
    </div>
</div>
