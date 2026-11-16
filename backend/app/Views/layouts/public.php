<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php $this->renderSection('title') ?> · <?= esc(setting('company_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css?v=' . @filemtime(FCPATH . 'assets/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-100 to-slate-50">
    <header class="bg-white shadow-sm">
        <div class="h-1.5 bg-gradient-to-r from-navy-900 via-crimson-700 to-brand-red"></div>
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
            <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="<?= esc(setting('company_name')) ?>" class="h-12 w-auto">
            <div class="hidden text-right text-xs text-slate-500 sm:block"><?= esc(setting('company_phone')) ?><br><?= esc(setting('company_email')) ?></div>
        </div>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-8"><?= $this->renderSection('content') ?></main>
    <footer class="pb-10 text-center text-xs text-slate-400">
        <?= esc(setting('company_legal_name')) ?><?= setting('company_vat_number') ? ' · VAT ' . esc(setting('company_vat_number')) : '' ?> · Secure payments powered by PayFast &amp; Ozow
    </footer>
    <?= view('partials/toasts') ?>
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    <script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
</body>
</html>
