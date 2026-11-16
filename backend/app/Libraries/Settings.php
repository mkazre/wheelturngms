<?php

namespace App\Libraries;

use Config\Database;

/**
 * Key/value application settings stored in the `settings` table.
 * Values are loaded once per request and cached in memory.
 */
class Settings
{
    private static ?array $cache = null;

    /** Keys that are stored encrypted at rest. */
    public const SECRET_KEYS = ['smtp_pass', 'payfast_passphrase', 'ozow_private_key', 'ozow_api_key', 'payfast_merchant_key', 'sms_password', 'ai_api_key'];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::connect()->table('settings')->get()->getResultArray() as $row) {
                    self::$cache[$row['key']] = self::decode($row['key'], $row['value']);
                }
            } catch (\Throwable $e) {
                // Table may not exist yet during install.
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        $db    = Database::connect();
        $value = is_array($value) ? implode(',', $value) : (string) $value;
        $store = in_array($key, self::SECRET_KEYS, true) && $value !== '' ? self::encrypt($value) : $value;
        $now   = date('Y-m-d H:i:s');

        if ($db->table('settings')->where('key', $key)->countAllResults() > 0) {
            $db->table('settings')->where('key', $key)->update(['value' => $store, 'updated_at' => $now]);
        } else {
            $db->table('settings')->insert(['key' => $key, 'value' => $store, 'group' => $group, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function encrypt(string $value): string
    {
        try {
            return 'enc:' . base64_encode(service('encrypter')->encrypt($value));
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private static function decode(string $key, ?string $value): ?string
    {
        if ($value !== null && str_starts_with($value, 'enc:')) {
            try {
                return service('encrypter')->decrypt(base64_decode(substr($value, 4)));
            } catch (\Throwable $e) {
                return '';
            }
        }
        return $value;
    }
}
