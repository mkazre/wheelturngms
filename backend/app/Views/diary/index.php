<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Booking Diary<?= $this->endSection() ?>
<?= $this->section('head') ?>
<style>
    .fc { --fc-border-color: rgb(226 232 240 / .8); --fc-today-bg-color: rgb(155 22 49 / .05); --fc-button-bg-color: #0b0d2e; --fc-button-border-color: #0b0d2e; --fc-button-active-bg-color: #9b1631; --fc-button-active-border-color: #9b1631; --fc-button-hover-bg-color: #1c205c; font-size: 12px; }
    .dark .fc { --fc-border-color: rgb(255 255 255 / .08); --fc-page-bg-color: transparent; --fc-neutral-bg-color: rgb(255 255 255 / .04); --fc-list-event-hover-bg-color: rgb(255 255 255 / .05); color: #cbd5e1; }
    .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; }
    .fc .fc-button { border-radius: .6rem; padding: .35rem .7rem; font-weight: 600; text-transform: capitalize; }
    .fc-event { border-radius: .5rem; padding: 1px 3px; font-weight: 600; cursor: pointer; }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Booking diary', 'subtitle' => 'Drag bookings to reschedule. Amber items are online booking requests awaiting confirmation.',
    'actions' => '<a href="' . site_url('book') . '" target="_blank" class="btn-light">' . icon('eye', 'h-4 w-4') . ' Public booking page</a><a href="' . site_url('job-cards/new') . '" class="btn-primary">' . icon('plus', 'h-4 w-4') . ' New booking</a>']) ?>
<div class="grid gap-6 xl:grid-cols-4">
    <div class="card p-4 xl:col-span-3"><div id="calendar" class="min-h-[640px]"></div></div>
    <div class="space-y-6">
        <div class="card"><div class="card-header"><div class="card-title">Booking requests</div><span class="<?= $pending ? 'badge-amber' : 'badge-gray' ?>"><?= count($pending) ?></span></div>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                <?php foreach ($pending as $b): ?>
                    <li class="space-y-2 p-4 text-sm" x-data="{ decline: false }">
                        <div class="flex justify-between"><b><?= esc($b['name']) ?></b><span class="text-xs text-slate-500"><?= time_ago($b['created_at']) ?></span></div>
                        <div class="text-xs text-slate-600 dark:text-slate-300"><?= esc($b['service'] ?: 'Service') ?> · <b><?= fdatetime($b['preferred_at']) ?></b><br><?= esc(trim($b['registration'] . ' ' . $b['vehicle_desc'])) ?><br><?= esc($b['phone']) ?> <?= esc($b['email']) ?></div>
                        <?php if ($b['notes']): ?><p class="rounded-lg bg-slate-50 p-2 text-xs dark:bg-white/5"><?= esc($b['notes']) ?></p><?php endif; ?>
                        <div class="flex flex-wrap gap-1.5">
                            <form method="post" action="<?= site_url('diary/bookings/' . $b['id'] . '/confirm') ?>"><?= csrf_field() ?><button class="btn-primary btn-sm">Confirm</button></form>
                            <?= wa_button($b['phone'], 'Hi ' . $b['name'] . ', thanks for your booking request at ' . setting('company_name') . ' for ' . fdatetime($b['preferred_at']) . '.', 'Chat') ?>
                            <button type="button" @click="decline = !decline" class="btn-ghost btn-sm">Decline</button>
                        </div>
                        <form x-show="decline" x-cloak method="post" action="<?= site_url('diary/bookings/' . $b['id'] . '/decline') ?>" class="space-y-2"><?= csrf_field() ?>
                            <input name="message" placeholder="Message to customer (optional)" class="input py-1 text-xs"><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="notify" value="1" checked class="rounded"> Email customer</label><button class="btn-danger btn-sm">Decline booking</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (!$pending): ?><li class="p-6 text-center text-sm text-slate-500">No pending requests. Share your booking link: <br><code class="text-xs"><?= site_url('book') ?></code></li><?php endif; ?>
            </ul>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Legend</div></div><div class="card-body grid grid-cols-2 gap-2 text-xs">
            <?php foreach (job_statuses() as $k => $s): if (in_array($k, ['cancelled'], true)) continue; ?><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background: <?= $s['hex'] ?>"></span><?= $s['label'] ?></span><?php endforeach; ?>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Online request</span>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/fullcalendar.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cal = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: window.innerWidth < 768 ? 'listWeek' : 'timeGridWeek',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        firstDay: 1, slotMinTime: '07:00:00', slotMaxTime: '18:30:00', hiddenDays: [0], nowIndicator: true, editable: true, height: 'auto',
        businessHours: [{ daysOfWeek: [1, 2, 3, 4, 5], startTime: '07:30', endTime: '17:00' }, { daysOfWeek: [6], startTime: '08:00', endTime: '13:00' }],
        events: WT_BASE + 'diary/events',
        eventDidMount(info) { const p = info.event.extendedProps; info.el.title = [p.number, p.status, p.type, p.bay].filter(Boolean).join(' · '); },
        eventClick(info) { if (info.event.extendedProps.kind === 'booking') { info.jsEvent.preventDefault(); toast('Confirm this request from the panel on the right', 'info'); } },
        async eventDrop(info) {
            try { const r = await wtPost(WT_BASE + 'diary/move', { id: info.event.id, start: info.event.start.toISOString() }); toast(r.message); }
            catch (e) { info.revert(); toast(e.message, 'error'); }
        },
        dateClick(info) { if (info.view.type !== 'dayGridMonth') location.href = WT_BASE + 'job-cards/new?booked_for=' + encodeURIComponent(info.dateStr.slice(0, 16)); },
    });
    cal.render();
});
</script>
<?= $this->endSection() ?>
