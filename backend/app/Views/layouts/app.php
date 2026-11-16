<?php
$user = auth_user();
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$roleLabel = config('Permissions')->labels[$user['role'] ?? ''] ?? '';
$nav = [
    ['section' => 'Overview'],
    ['url' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'perm' => 'dashboard'],
    ['url' => 'workshop', 'label' => 'Workshop Board', 'icon' => 'board', 'perm' => 'workshop'],
    ['url' => 'diary', 'label' => 'Booking Diary', 'icon' => 'calendar', 'perm' => 'workshop'],
    ['url' => 'tasks', 'label' => 'My Tasks', 'icon' => 'check', 'perm' => 'workshop'],
    ['url' => 'assistant', 'label' => 'AI Assistant', 'icon' => 'zap', 'perm' => 'job_cards'],
    ['section' => 'Workshop'],
    ['url' => 'job-cards', 'label' => 'Job Cards', 'icon' => 'wrench', 'perm' => 'job_cards'],
    ['url' => 'canned-jobs', 'label' => 'Service Packages', 'icon' => 'clipboard', 'perm' => 'inventory'],
    ['url' => 'loans', 'label' => 'Courtesy Cars', 'icon' => 'car', 'perm' => 'workshop'],
    ['url' => 'quotes', 'label' => 'Quotes', 'icon' => 'quote', 'perm' => 'quotes'],
    ['url' => 'customers', 'label' => 'Customers', 'icon' => 'users', 'perm' => 'customers'],
    ['url' => 'follow-ups', 'label' => 'Follow-ups', 'icon' => 'phone', 'perm' => 'customers'],
    ['url' => 'campaigns', 'label' => 'Campaigns', 'icon' => 'mail', 'perm' => 'customers'],
    ['url' => 'feedback', 'label' => 'Customer Feedback', 'icon' => 'zap', 'perm' => 'customers'],
    ['url' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'perm' => 'vehicles'],
    ['url' => 'inventory', 'label' => 'Parts & Inventory', 'icon' => 'box', 'perm' => 'inventory'],
    ['url' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'truck', 'perm' => 'suppliers'],
    ['url' => 'purchase-orders', 'label' => 'Purchase Orders', 'icon' => 'clipboard', 'perm' => 'purchases'],
    ['section' => 'Finance'],
    ['url' => 'invoices', 'label' => 'Invoices', 'icon' => 'invoice', 'perm' => 'invoices'],
    ['url' => 'credit-notes', 'label' => 'Credit Notes', 'icon' => 'refresh', 'perm' => 'invoices'],
    ['url' => 'payments', 'label' => 'Payments', 'icon' => 'card', 'perm' => 'payments'],
    ['url' => 'bills', 'label' => 'Supplier Bills', 'icon' => 'file', 'perm' => 'purchases'],
    ['url' => 'expenses', 'label' => 'Expenses & Slips', 'icon' => 'receipt', 'perm' => 'expenses'],
    ['url' => 'accounting', 'label' => 'Accounting', 'icon' => 'book', 'perm' => 'accounting'],
    ['url' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'pie', 'perm' => 'reports'],
    ['section' => 'Admin'],
    ['url' => 'users', 'label' => 'Team', 'icon' => 'shield', 'perm' => 'users'],
    ['url' => 'admin/audit', 'label' => 'Audit Log', 'icon' => 'clipboard', 'perm' => 'settings'],
    ['url' => 'admin/import', 'label' => 'Import Data', 'icon' => 'upload', 'perm' => 'settings'],
    ['url' => 'admin/backups', 'label' => 'Backups', 'icon' => 'download', 'perm' => 'settings'],
    ['url' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'perm' => 'settings'],
];
?>
<!doctype html>
<html lang="en" class="h-full" x-data="themeStore()" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <title><?php $this->renderSection('title') ?> · <?= esc(setting('company_name', 'Wheelsturn Automotive')) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tom-select.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=' . @filemtime(FCPATH . 'assets/css/app.css')) ?>">
    <script>
        // Apply theme before paint to avoid flashing
        (function () { try { var t = localStorage.getItem('wt-theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {} })();
    </script>
    <?= $this->renderSection('head') ?>
</head>
<body class="h-full" x-data="{ sidebar: false }">
<div class="flex min-h-full">
    <!-- Sidebar -->
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-navy-950/60 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-gradient-to-b from-navy-900 via-navy-950 to-navy-950 transition-transform duration-300 lg:translate-x-0">
        <div class="px-5 pb-3 pt-5">
            <a href="<?= site_url('dashboard') ?>" class="block rounded-2xl bg-white px-4 py-3 shadow-lg ring-1 ring-white/10">
                <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="Wheelsturn Automotive" class="h-12 w-full object-contain">
            </a>
        </div>
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-4 pb-6">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['section'])): ?>
                    <div class="nav-section"><?= esc($item['section']) ?></div>
                <?php elseif (can($item['perm'])): ?>
                    <a href="<?= site_url($item['url']) ?>" class="nav-link <?= nav_active($item['url']) ?>">
                        <?= icon($item['icon'], 'h-[18px] w-[18px] shrink-0') ?>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-crimson-600 to-brand-red text-xs font-bold text-white"><?= esc(initials($fullName)) ?></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-white"><?= esc($fullName) ?></div>
                    <div class="truncate text-[11px] text-slate-400"><?= esc($roleLabel) ?></div>
                </div>
                <a href="<?= site_url('logout') ?>" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white" title="Sign out"><?= icon('logout', 'h-4 w-4') ?></a>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
        <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl dark:border-white/10 dark:bg-navy-950/80">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button class="btn-ghost -ml-2 p-2 lg:hidden" @click="sidebar = true" aria-label="Open menu"><?= icon('menu') ?></button>

                <form action="<?= site_url('search') ?>" method="get" class="relative flex-1 max-w-xl">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><?= icon('search', 'h-4 w-4') ?></span>
                    <input type="search" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Search customers, reg numbers, VIN, job cards, invoices…  ( / )"
                           x-ref="search" @keydown.window.slash.prevent="$el.focus()"
                           class="input rounded-full border-transparent bg-slate-100 pl-10 dark:bg-white/5">
                </form>

                <div class="ml-auto flex items-center gap-1.5">
                    <?php $branches = db_connect()->table('branches')->select('id, name')->where('is_active', 1)->get()->getResultArray(); if (count($branches) > 1 && session('role') !== 'mechanic'): ?>
                        <form method="post" action="<?= site_url('branch') ?>" class="hidden md:block"><?= csrf_field() ?>
                            <select name="branch_id" onchange="this.form.submit()" class="input w-auto rounded-full border-transparent bg-slate-100 py-1.5 text-xs font-semibold dark:bg-white/5" title="Active branch for new records">
                                <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (int) session('branch_id') === (int) $b['id'] ? 'selected' : '' ?>><?= esc($b['name']) ?></option><?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                    <button type="button" onclick="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))" class="hidden items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 lg:flex dark:bg-white/5" title="Command palette">Quick jump <kbd class="rounded border border-slate-300 px-1 text-[10px] dark:border-white/20">Ctrl K</kbd></button>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="btn-primary btn-sm hidden sm:inline-flex"><?= icon('plus', 'h-4 w-4') ?> New</button>
                        <button @click="open = !open" class="btn-primary p-2 sm:hidden" aria-label="Create"><?= icon('plus', 'h-4 w-4') ?></button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-white/10 dark:bg-navy-900">
                            <?php foreach ([['job-cards/new', 'Job card', 'wrench', 'job_cards'], ['quotes/new', 'Quote', 'quote', 'quotes'], ['invoices/new', 'Invoice', 'invoice', 'invoices'], ['customers/new', 'Customer', 'users', 'customers'], ['vehicles/new', 'Vehicle', 'car', 'vehicles'], ['expenses/new', 'Expense / slip', 'receipt', 'expenses']] as [$u, $l, $i, $p]): ?>
                                <?php if (can($p)): ?>
                                    <a href="<?= site_url($u) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/5"><?= icon($i, 'h-4 w-4 text-crimson-600') ?> <?= $l ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php $unread = \App\Libraries\Notify::unread((int) session('user_id'), (string) session('role')); ?>
                    <div x-data="{ open: false, items: [], unread: <?= $unread ?>, overdue: 0, async load() { const r = await fetch(WT_BASE + 'notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); this.items = j.items; this.overdue = j.overdueTasks; this.unread = 0; } }" class="relative">
                        <button @click="open = !open; if (open) load()" class="btn-ghost relative p-2" aria-label="Notifications">
                            <?= icon('bell', 'h-5 w-5') ?>
                            <span x-show="unread > 0" x-text="unread > 9 ? '9+' : unread" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-brand-red px-1 text-[10px] font-bold text-white"></span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-navy-900">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10"><span class="text-sm font-bold">Notifications</span><a href="<?= site_url('tasks') ?>" class="text-xs font-semibold text-crimson-700 dark:text-crimson-400">My tasks <span x-show="overdue" class="badge-red" x-text="overdue + ' overdue'"></span></a></div>
                            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-white/10">
                                <template x-for="n in items" :key="n.id"><li><a :href="n.link || '#'" class="flex gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-white/5" :class="n.unread && 'bg-crimson-50/40 dark:bg-crimson-500/5'">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.unread ? 'bg-brand-red' : 'bg-slate-300'"></span>
                                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold" x-text="n.title"></span><span class="block truncate text-xs text-slate-500" x-text="n.body"></span><span class="text-[10px] text-slate-400" x-text="n.ago"></span></span>
                                </a></li></template>
                                <li x-show="!items.length" class="px-4 py-8 text-center text-sm text-slate-500">You're all caught up.</li>
                            </ul>
                        </div>
                    </div>
                    <button @click="toggle()" class="btn-ghost p-2" aria-label="Toggle dark mode">
                        <span x-show="!dark"><?= icon('moon', 'h-5 w-5') ?></span>
                        <span x-show="dark" x-cloak><?= icon('sun', 'h-5 w-5') ?></span>
                    </button>
                    <a href="<?= site_url('profile') ?>" class="hidden h-9 w-9 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white sm:flex dark:bg-white/10" title="My profile"><?= esc(initials($fullName)) ?></a>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <?= $this->renderSection('content') ?>
        </main>

        <footer class="px-4 pb-6 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
            © <?= date('Y') ?> <?= esc(setting('company_legal_name', 'Wheelsturn Automotive')) ?> · Garage Management System
        </footer>
    </div>
</div>

<?php
$paletteItems = [];
foreach ($nav as $item) {
    if (isset($item['url']) && can($item['perm'])) {
        $paletteItems[] = ['group' => 'Go to', 'label' => $item['label'], 'url' => site_url($item['url'])];
    }
}
foreach ([['job-cards/new', 'New job card', 'job_cards'], ['quotes/new', 'New quote', 'quotes'], ['invoices/new', 'New invoice', 'invoices'], ['customers/new', 'New customer', 'customers'], ['vehicles/new', 'New vehicle', 'vehicles'], ['expenses/new', 'Capture expense / slip', 'expenses'], ['bills/new', 'Capture supplier bill', 'purchases'], ['purchase-orders/new', 'New purchase order', 'purchases'], ['accounting/journals/new', 'New journal', 'accounting'], ['assistant', 'Ask the AI assistant', 'job_cards'], ['workshop/tv', 'Open TV display', 'workshop']] as [$u, $l, $p]) {
    if (can($p)) {
        $paletteItems[] = ['group' => 'Create', 'label' => $l, 'url' => site_url($u)];
    }
}
?>
<div x-data="{ open: false, q: '', sel: 0, remote: [], items: <?= esc(json_encode($paletteItems), 'attr') ?>,
        get results() { const q = this.q.toLowerCase(); const local = this.items.filter(i => !q || i.label.toLowerCase().includes(q)).slice(0, q ? 8 : 12); return [...local, ...this.remote]; },
        async search() { this.sel = 0; if (this.q.length < 2) { this.remote = []; return; } try { const r = await fetch(WT_BASE + 'palette?q=' + encodeURIComponent(this.q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); this.remote = await r.json(); } catch (e) { this.remote = []; } },
        go(i) { const r = this.results[i]; if (r) location.href = r.url; } }"
     @keydown.window.ctrl.k.prevent="open = !open; $nextTick(() => $refs.pq && $refs.pq.focus())" @keydown.window.meta.k.prevent="open = !open; $nextTick(() => $refs.pq && $refs.pq.focus())">
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center bg-navy-950/60 p-4 pt-[12vh] backdrop-blur-sm" @keydown.escape="open = false">
        <div @click.outside="open = false" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-navy-900 dark:ring-white/10">
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 dark:border-white/10"><?= icon('search', 'h-5 w-5 text-slate-400') ?>
                <input x-ref="pq" x-model="q" @input.debounce.200ms="search()" @keydown.down.prevent="sel = Math.min(sel + 1, results.length - 1)" @keydown.up.prevent="sel = Math.max(sel - 1, 0)" @keydown.enter.prevent="go(sel)" data-no-voice
                       placeholder="Jump to a page, action, customer, registration, job or invoice…" class="w-full border-0 bg-transparent py-4 text-sm focus:ring-0 dark:text-white">
                <kbd class="rounded border border-slate-200 px-1.5 text-[10px] text-slate-400 dark:border-white/10">ESC</kbd></div>
            <ul class="max-h-96 overflow-y-auto p-2">
                <template x-for="(r, i) in results" :key="i"><li><a :href="r.url" @mouseenter="sel = i" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm" :class="sel === i ? 'bg-crimson-50 text-crimson-800 dark:bg-white/10 dark:text-white' : ''">
                    <span><span class="mr-2 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="r.group"></span><span class="font-medium" x-text="r.label"></span></span><span class="text-xs text-slate-400" x-text="r.hint || ''"></span></a></li></template>
                <li x-show="!results.length" class="px-3 py-6 text-center text-sm text-slate-500">No matches.</li>
            </ul>
        </div>
    </div>
</div>
<?= view('partials/toasts') ?>

<script>window.WT_BASE = <?= json_encode(rtrim(site_url(), '/') . '/') ?>;</script>
<script src="<?= base_url('assets/vendor/tom-select.min.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js?v=' . @filemtime(FCPATH . 'assets/js/app.js')) ?>"></script>
<?= $this->renderSection('scripts') ?>
<script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
</body>
</html>
