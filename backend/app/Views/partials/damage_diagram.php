<?php
/**
 * Top-view vehicle damage diagram. @var array $marks  @var bool $editable  @var ?string $saveUrl
 * Zones are drawn as simple panels so the diagram works for any car.
 */
$zones = [
    'front_bumper' => ['Front bumper', 'M70 12 h160 a20 20 0 0 1 20 20 v14 h-200 v-14 a20 20 0 0 1 20-20z'],
    'bonnet'       => ['Bonnet', 'M58 50 h184 v104 h-184z'],
    'windscreen'   => ['Windscreen', 'M66 158 h168 l-14 44 h-140z'],
    'roof'         => ['Roof', 'M80 206 h140 v150 h-140z'],
    'rear_window'  => ['Rear window', 'M80 360 h140 l14 40 h-168z'],
    'boot'         => ['Boot / tailgate', 'M58 404 h184 v86 h-184z'],
    'rear_bumper'  => ['Rear bumper', 'M50 494 h200 v14 a20 20 0 0 1 -20 20 h-160 a20 20 0 0 1 -20-20z'],
    'lf_fender'    => ['Left front fender', 'M20 60 h34 v110 h-34z'],
    'lf_door'      => ['Left front door', 'M20 174 h56 v100 h-56z'],
    'lr_door'      => ['Left rear door', 'M20 278 h56 v100 h-56z'],
    'lr_quarter'   => ['Left rear quarter', 'M20 382 h34 v110 h-34z'],
    'rf_fender'    => ['Right front fender', 'M246 60 h34 v110 h-34z'],
    'rf_door'      => ['Right front door', 'M224 174 h56 v100 h-56z'],
    'rr_door'      => ['Right rear door', 'M224 278 h56 v100 h-56z'],
    'rr_quarter'   => ['Right rear quarter', 'M246 382 h34 v110 h-34z'],
    'l_mirror'     => ['Left mirror', 'M2 168 h16 v18 h-16z'],
    'r_mirror'     => ['Right mirror', 'M282 168 h16 v18 h-16z'],
];
$types = ['scratch' => ['Scratch', '#f59e0b'], 'dent' => ['Dent', '#ef1c25'], 'chip' => ['Chip', '#8b5cf6'], 'crack' => ['Crack', '#0ea5e9'], 'rust' => ['Rust', '#92400e'], 'other' => ['Other', '#64748b']];
$editable = $editable ?? false;
?>
<div x-data="{ marks: <?= esc(json_encode((object) ($marks ?? [])), 'attr') ?>, zone: null, type: 'scratch', note: '',
        labels: <?= esc(json_encode(array_map(static fn ($z) => $z[0], $zones)), 'attr') ?>, colors: <?= esc(json_encode(array_map(static fn ($t) => $t[1], $types)), 'attr') ?>,
        pick(z) { <?= $editable ? "this.zone = z; this.type = (this.marks[z] || {}).type || 'scratch'; this.note = (this.marks[z] || {}).note || '';" : '' ?> },
        async save() { try { const r = await wtPost('<?= esc($saveUrl ?? '', 'js') ?>', { marks: JSON.stringify(this.marks) }); toast('Damage map saved (' + r.count + ' marks)'); } catch (e) { toast(e.message, 'error'); } },
        set() { this.marks[this.zone] = { type: this.type, note: this.note }; this.zone = null; this.save(); },
        clear() { delete this.marks[this.zone]; this.zone = null; this.save(); } }" class="grid gap-6 md:grid-cols-2">
    <svg viewBox="0 0 300 540" class="mx-auto h-[420px] w-auto select-none" role="img" aria-label="Vehicle damage diagram">
        <rect x="14" y="8" width="272" height="524" rx="60" class="fill-slate-50 stroke-slate-300 dark:fill-white/5 dark:stroke-white/20" stroke-width="2"/>
        <?php foreach ($zones as $key => [$label, $path]): ?>
            <path d="<?= $path ?>" @click="pick('<?= $key ?>')" :fill="marks['<?= $key ?>'] ? colors[marks['<?= $key ?>'].type] : 'transparent'" :fill-opacity="marks['<?= $key ?>'] ? .75 : 0"
                  class="stroke-slate-400 transition dark:stroke-white/30 <?= $editable ? 'cursor-pointer hover:fill-crimson-200' : '' ?>" stroke-width="1.5"><title><?= $label ?></title></path>
        <?php endforeach; ?>
        <text x="150" y="105" text-anchor="middle" class="fill-slate-400 text-[11px] font-semibold">FRONT</text>
        <text x="150" y="450" text-anchor="middle" class="fill-slate-400 text-[11px] font-semibold">REAR</text>
    </svg>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2 text-xs"><?php foreach ($types as $k => [$l, $c]): ?><span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded" style="background: <?= $c ?>"></span><?= $l ?></span><?php endforeach; ?></div>
        <?php if ($editable): ?>
            <div x-show="zone" x-cloak class="rounded-2xl border border-crimson-200 bg-crimson-50/50 p-4 dark:border-crimson-500/30 dark:bg-crimson-500/10">
                <div class="mb-2 text-sm font-bold" x-text="labels[zone]"></div>
                <div class="mb-2 flex flex-wrap gap-1"><?php foreach ($types as $k => [$l, $c]): ?><button type="button" @click="type = '<?= $k ?>'" :class="type === '<?= $k ?>' ? 'ring-2 ring-offset-1 ring-navy-900' : ''" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-white" style="background: <?= $c ?>"><?= $l ?></button><?php endforeach; ?></div>
                <input x-model="note" placeholder="Note (size, location…)" class="input mb-2 py-1.5 text-sm">
                <div class="flex gap-2"><button type="button" @click="set()" class="btn-primary btn-sm">Mark damage</button><button type="button" @click="clear()" class="btn-light btn-sm">No damage</button><button type="button" @click="zone = null" class="btn-ghost btn-sm">Cancel</button></div>
            </div>
            <p x-show="!zone" class="text-sm text-slate-500">Tap a panel to record existing damage at check-in. This protects the workshop against disputes; it prints on the job card the customer signs.</p>
        <?php endif; ?>
        <ul class="space-y-1.5 text-sm">
            <template x-for="(m, z) in marks" :key="z"><li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" :style="'background:' + colors[m.type]"></span><b x-text="labels[z]"></b><span class="text-slate-500" x-text="m.type + (m.note ? ' · ' + m.note : '')"></span></li></template>
            <li x-show="!Object.keys(marks).length" class="text-slate-500">No existing damage recorded.</li>
        </ul>
    </div>
</div>
