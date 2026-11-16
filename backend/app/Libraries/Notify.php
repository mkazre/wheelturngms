<?php

namespace App\Libraries;

use Config\Database;

/**
 * In-app notifications, SMS (BulkSMS / Clickatell) and WhatsApp click-to-chat links.
 */
class Notify
{
    /** Notify one user, a role (e.g. 'manager'), or all staff (both null). */
    public static function staff(string $title, string $body = '', ?string $url = null, string $icon = 'bell', ?int $userId = null, ?string $role = null): void
    {
        try {
            Database::connect()->table('notifications')->insert([
                'user_id' => $userId, 'role' => $role, 'title' => mb_substr($title, 0, 191), 'body' => mb_substr($body, 0, 500),
                'url' => $url, 'icon' => $icon, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Notification failed: ' . $e->getMessage());
        }
    }

    /** Notifications visible to the current user (personal, their role, or broadcast). */
    public static function forUser(int $userId, string $role, int $limit = 20): array
    {
        return Database::connect()->table('notifications')
            ->groupStart()->where('user_id', $userId)->orGroupStart()->where('user_id', null)->groupStart()->where('role', null)->orWhere('role', $role)->groupEnd()->groupEnd()->groupEnd()
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /** Unread count, using a per-user "last seen" marker for broadcast notifications. */
    public static function unread(int $userId, string $role): int
    {
        $seen = (string) Settings::get('notif_seen_' . $userId, '1970-01-01 00:00:00');
        return Database::connect()->table('notifications')
            ->groupStart()->where('user_id', $userId)->orGroupStart()->where('user_id', null)->groupStart()->where('role', null)->orWhere('role', $role)->groupEnd()->groupEnd()->groupEnd()
            ->where('created_at >', $seen)->countAllResults();
    }

    // ------------------------------------------------------------ SMS

    public static function normalisePhone(?string $phone): ?string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0') && strlen($d) === 10) {
            $d = '27' . substr($d, 1);
        }
        return strlen($d) >= 10 ? $d : null;
    }

    public static function sms(?string $phone, string $message, ?string $entityType = null, ?int $entityId = null): bool
    {
        $provider = (string) Settings::get('sms_provider', '');
        $to = self::normalisePhone($phone);
        if ($provider === '' || !$to) {
            return false;
        }
        $message = mb_substr($message, 0, 459); // 3 SMS parts max
        $ok = false;
        $error = null;
        try {
            $http = service('curlrequest', ['timeout' => 15, 'http_errors' => false], null, false);
            if ($provider === 'bulksms') {
                $res = $http->post('https://api.bulksms.com/v1/messages', [
                    'auth' => [Settings::get('sms_username'), Settings::get('sms_password')],
                    'json' => ['to' => '+' . $to, 'body' => $message, 'from' => Settings::get('sms_sender') ?: null],
                ]);
                $ok = $res->getStatusCode() < 300;
            } elseif ($provider === 'clickatell') {
                $res = $http->post('https://platform.clickatell.com/v1/message', [
                    'headers' => ['Authorization' => Settings::get('sms_password'), 'Content-Type' => 'application/json'],
                    'json' => ['messages' => [['channel' => 'sms', 'to' => $to, 'content' => $message]]],
                ]);
                $ok = $res->getStatusCode() < 300;
            }
            if (!$ok) {
                $error = isset($res) ? mb_substr($res->getBody(), 0, 500) : 'Unknown provider';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        Database::connect()->table('email_logs')->insert([
            'recipient' => 'SMS +' . $to, 'subject' => mb_substr($message, 0, 120), 'entity_type' => $entityType, 'entity_id' => $entityId,
            'status' => $ok ? 'sent' : 'failed', 'error' => $error, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $ok;
    }

    // ------------------------------------------------------------ WhatsApp

    /** Click-to-chat link with a pre-filled message (works with WhatsApp & WhatsApp Business, no API costs). */
    public static function whatsappLink(?string $phone, string $text): ?string
    {
        $to = self::normalisePhone($phone);
        return $to ? 'https://wa.me/' . $to . '?text=' . rawurlencode($text) : null;
    }

    public static function qr(string $text, int $scale = 5): string
    {
        $o = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG, 'scale' => $scale, 'outputBase64' => true, 'addQuietzone' => true, 'quietzoneSize' => 1]);
        return (new \chillerlan\QRCode\QRCode($o))->render($text);
    }
}
