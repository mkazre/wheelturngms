<?php

namespace App\Libraries;

/**
 * RFC 6238 time-based one-time passwords (Google Authenticator, Microsoft Authenticator, Authy…).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $bits = '';
        foreach (str_split($raw) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code and one step either side (clock drift). */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        foreach ([-1, 0, 1] as $step) {
            if (hash_equals(self::code($secret, time() + $step * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $email): string
    {
        $issuer = rawurlencode((string) Settings::get('company_name', 'Wheelsturn'));
        return 'otpauth://totp/' . $issuer . ':' . rawurlencode($email) . '?secret=' . $secret . '&issuer=' . $issuer . '&digits=6&period=30';
    }
}
