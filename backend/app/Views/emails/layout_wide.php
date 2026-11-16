<?php /** @var string $subject @var string $body (already-safe HTML) */ ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= esc($subject) ?></title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="max-width:720px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.08);">
            <tr><td style="height:6px;background:#0b0d2e;"><div style="height:6px;width:45%;margin-left:55%;background:#ef1c25;"></div></td></tr>
            <tr><td style="padding:28px 32px 8px 32px;"><img src="<?= esc($logoSrc ?? base_url('assets/img/logo-sm.png'), 'attr') ?>" alt="<?= esc(setting('company_name')) ?>" width="200" style="display:block;height:auto;border:0;"></td></tr>
            <tr><td style="padding:12px 32px 28px 32px;font-size:15px;line-height:1.6;color:#334155;"><?= $body ?></td></tr>
            <tr><td style="padding:20px 32px;background:#0b0d2e;color:#94a3b8;font-size:12px;line-height:1.6;">
                <b style="color:#ffffff;"><?= esc(setting('company_legal_name')) ?></b><br>
                <?= nl2br(esc(setting('company_address'))) ?><br>
                <?= esc(implode(' · ', array_filter([setting('company_phone'), setting('company_email'), setting('company_website')]))) ?>
                <?php if (setting('company_vat_number')): ?><br>VAT No: <?= esc(setting('company_vat_number')) ?><?php endif; ?>
            </td></tr>
        </table>
        <p style="font-size:11px;color:#94a3b8;margin-top:14px;">You are receiving this because you are a customer of <?= esc(setting('company_name')) ?>. Your information is processed in line with POPIA.</p>
    </td></tr>
</table>
</body></html>
