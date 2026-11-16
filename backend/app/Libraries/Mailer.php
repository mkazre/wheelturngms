<?php

namespace App\Libraries;

use Config\Database;

/**
 * Sends branded HTML email using the SMTP settings configured in Settings → Email.
 */
class Mailer
{
    /**
     * @param string|array $to
     * @param array $attachments [['content' => binary, 'name' => 'file.pdf', 'mime' => 'application/pdf', 'inline' => false]]
     */
    public static function send($to, string $subject, string $bodyHtml, array $attachments = [], ?string $entityType = null, ?int $entityId = null, ?string $replyTo = null, ?string $layout = null): bool
    {
        $recipients = array_filter(array_map('trim', is_array($to) ? $to : explode(',', (string) $to)));
        if ($recipients === []) {
            return false;
        }

        $email = \Config\Services::email(self::config(), false);
        $email->setFrom(Settings::get('mail_from_email', 'noreply@wheelsturn.co.za'), Settings::get('mail_from_name', Settings::get('company_name')));
        $email->setTo($recipients);
        if ($bcc = Settings::get('mail_bcc')) {
            $email->setBCC($bcc);
        }
        if ($replyTo || ($replyTo = Settings::get('company_email'))) {
            $email->setReplyTo($replyTo);
        }
        $email->setSubject($subject);

        // Attachments first so inline images (charts) get a CID we can reference: {{cid:file.png}}
        foreach ($attachments as $a) {
            $inline = !empty($a['inline']);
            $email->attach($a['content'], $inline ? 'inline' : 'attachment', $a['name'], $a['mime'] ?? 'application/pdf');
            if ($inline && ($cid = $email->setAttachmentCID($a['name']))) {
                $bodyHtml = str_replace('{{cid:' . $a['name'] . '}}', 'cid:' . $cid, $bodyHtml);
            }
        }
        // Embed the logo so it shows even when the mail client blocks remote images
        $logoSrc = base_url('assets/img/logo-sm.png');
        $logoPath = FCPATH . 'assets/img/logo-sm.png';
        if (is_file($logoPath)) {
            $email->attach((string) file_get_contents($logoPath), 'inline', 'wheelsturn-logo.png', 'image/png');
            if ($cid = $email->setAttachmentCID('wheelsturn-logo.png')) {
                $logoSrc = 'cid:' . $cid;
            }
        }
        $email->setMessage(view($layout ?? 'emails/layout', ['subject' => $subject, 'body' => $bodyHtml, 'logoSrc' => $logoSrc]));

        $ok    = false;
        $error = null;
        try {
            $ok = $email->send(false);
            if (!$ok) {
                $error = strip_tags($email->printDebugger(['headers']));
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        Database::connect()->table('email_logs')->insert([
            'recipient' => implode(', ', $recipients), 'subject' => $subject, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'status' => $ok ? 'sent' : 'failed', 'error' => $error ? mb_substr($error, 0, 2000) : null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$ok) {
            log_message('error', 'Email failed to {to}: {err}', ['to' => implode(',', $recipients), 'err' => $error]);
        }
        return $ok;
    }

    public static function config(): array
    {
        return [
            'protocol'   => Settings::get('mail_protocol', 'smtp'),
            'SMTPHost'   => Settings::get('smtp_host', ''),
            'SMTPUser'   => Settings::get('smtp_user', ''),
            'SMTPPass'   => Settings::get('smtp_pass', ''),
            'SMTPPort'   => (int) Settings::get('smtp_port', 587),
            'SMTPCrypto' => Settings::get('smtp_crypto', 'tls'),
            'SMTPTimeout'=> 20,
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
            'newline'    => "\r\n",
            'CRLF'       => "\r\n",
            'wordWrap'   => false,
        ];
    }

    /** Replace {placeholders} in a template and convert plain-text line breaks to HTML. */
    public static function template(string $key, array $vars): array
    {
        $vars += ['company' => Settings::get('company_name')];
        $search  = array_map(static fn ($k) => '{' . $k . '}', array_keys($vars));
        $subject = str_replace($search, array_values($vars), (string) Settings::get("tpl_{$key}_subject", ''));
        $bodyRaw = str_replace($search, array_map(static fn ($v) => esc((string) $v), array_values($vars)), esc((string) Settings::get("tpl_{$key}_body", '')));
        // Turn URLs into buttons/links
        $body = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#9b1631;font-weight:600">$1</a>', nl2br($bodyRaw));
        return [$subject, $body];
    }
}
