<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Redirecting to <?= esc($gateway) ?>…</title>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>"></head>
<body class="flex min-h-screen items-center justify-center bg-slate-50">
<div class="text-center">
    <img src="<?= base_url('assets/img/logo-sm.png') ?>" alt="" class="mx-auto h-12">
    <div class="mx-auto mt-8 h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-crimson-600"></div>
    <p class="mt-4 text-sm text-slate-600">Taking you to <b><?= esc($gateway) ?></b> secure checkout…</p>
    <form id="gw" method="post" action="<?= esc($action, 'attr') ?>">
        <?php foreach ($fields as $k => $v): ?><input type="hidden" name="<?= esc($k, 'attr') ?>" value="<?= esc((string) $v, 'attr') ?>"><?php endforeach; ?>
        <noscript><button class="btn-primary mt-4">Continue to payment</button></noscript>
    </form>
</div>
<script>document.getElementById('gw').submit();</script>
</body></html>
