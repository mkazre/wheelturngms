<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php $this->renderSection('title') ?> · Wheelsturn Automotive</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=' . @filemtime(FCPATH . 'assets/css/app.css')) ?>">
    <script>(function () { try { var t = localStorage.getItem('wt-theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {} })();</script>
</head>
<body class="h-full">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
        <div class="mx-auto w-full max-w-sm">
            <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="Wheelsturn Automotive" class="mb-10 h-16 w-auto rounded-xl bg-white p-1">
            <?= $this->renderSection('content') ?>
        </div>
    </div>
    <div class="relative hidden overflow-hidden bg-navy-950 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(239,28,37,.35),transparent_45%),radial-gradient(circle_at_80%_80%,rgba(82,91,181,.45),transparent_50%)]"></div>
        <img src="<?= base_url('assets/img/emblem.png') ?>" alt="" class="absolute -right-24 -top-16 w-[520px] rounded-full opacity-[.08] mix-blend-screen">
        <div class="relative flex h-full flex-col justify-end p-16 text-white">
            <div class="mb-6 inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span> Workshop OS</div>
            <h2 class="max-w-md text-4xl font-extrabold leading-tight tracking-tight">Run every bay, job card and rand from one place.</h2>
            <p class="mt-4 max-w-md text-slate-300">Job cards, digital inspections, quotes, SARS-compliant invoicing, online payments with PayFast &amp; Ozow, and real-time analytics.</p>
            <div class="mt-10 grid max-w-md grid-cols-3 gap-4 text-sm">
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10"><div class="text-2xl font-bold">15%</div><div class="text-slate-400">VAT ready</div></div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10"><div class="text-2xl font-bold">24/7</div><div class="text-slate-400">Online pay</div></div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10"><div class="text-2xl font-bold">Live</div><div class="text-slate-400">Analytics</div></div>
            </div>
        </div>
    </div>
</div>
<?= view('partials/toasts') ?>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
</body>
</html>
