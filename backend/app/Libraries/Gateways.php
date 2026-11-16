<?php

namespace App\Libraries;

/**
 * PayFast and Ozow integrations (South African online payments).
 */
class Gateways
{
    // ---------------------------------------------------------------- PayFast

    public static function payfastUrl(): string
    {
        return (int) Settings::get('payfast_sandbox', 1) === 1
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';
    }

    public static function payfastSignature(array $data, ?string $passphrase): string
    {
        $parts = [];
        foreach ($data as $k => $v) {
            if ($k === 'signature' || $v === '' || $v === null) {
                continue;
            }
            $parts[] = $k . '=' . urlencode(trim((string) $v));
        }
        $str = implode('&', $parts);
        if ($passphrase !== null && $passphrase !== '') {
            $str .= '&passphrase=' . urlencode(trim($passphrase));
        }
        return md5($str);
    }

    /** Build the ordered field list PayFast expects. */
    public static function payfastFields(array $invoice, array $customer, float $amount): array
    {
        $token = $invoice['public_token'];
        $data = [
            'merchant_id'      => Settings::get('payfast_merchant_id'),
            'merchant_key'     => Settings::get('payfast_merchant_key'),
            'return_url'       => site_url("pay/{$token}/success"),
            'cancel_url'       => site_url("pay/{$token}?cancelled=1"),
            'notify_url'       => site_url('webhooks/payfast'),
            'name_first'       => mb_substr((string) ($customer['first_name'] ?: $customer['company_name']), 0, 100),
            'name_last'        => mb_substr((string) $customer['last_name'], 0, 100),
            'email_address'    => (string) $customer['email'],
            'm_payment_id'     => $invoice['number'] . '-' . time(),
            'amount'           => number_format($amount, 2, '.', ''),
            'item_name'        => 'Invoice ' . $invoice['number'],
            'item_description' => mb_substr(Settings::get('company_name') . ' – Tax invoice ' . $invoice['number'], 0, 255),
            'custom_int1'      => (string) $invoice['id'],
        ];
        $data['signature'] = self::payfastSignature($data, Settings::get('payfast_passphrase'));
        return $data;
    }

    /**
     * Validate a PayFast ITN post. Returns [bool valid, string reason].
     */
    public static function payfastValidateItn(array $post, float $expectedAmount): array
    {
        $sig = $post['signature'] ?? '';
        if (!hash_equals(self::payfastSignature($post, Settings::get('payfast_passphrase')), (string) $sig)) {
            return [false, 'Invalid signature'];
        }
        // Source must resolve to a PayFast host
        $validHosts = ['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'];
        $validIps = [];
        foreach ($validHosts as $h) {
            $ips = gethostbynamel($h);
            if ($ips) {
                $validIps = array_merge($validIps, $ips);
            }
        }
        $remote = service('request')->getIPAddress();
        if ($validIps && !in_array($remote, array_unique($validIps), true)) {
            return [false, "Source IP {$remote} is not PayFast"];
        }
        if (abs((float) ($post['amount_gross'] ?? 0) - $expectedAmount) > 0.01 && (int) Settings::get('allow_partial_online_payment', 0) !== 1) {
            return [false, 'Amount mismatch'];
        }
        // Server-to-server confirmation
        $host = (int) Settings::get('payfast_sandbox', 1) === 1 ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
        $parts = [];
        foreach ($post as $k => $v) {
            if ($k !== 'signature') {
                $parts[] = $k . '=' . urlencode(trim((string) $v));
            }
        }
        try {
            $res = service('curlrequest', ['timeout' => 15], null, false)->post("https://{$host}/eng/query/validate", [
                'body' => implode('&', $parts), 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'http_errors' => false,
            ]);
            if (trim($res->getBody()) !== 'VALID') {
                return [false, 'PayFast server validation failed'];
            }
        } catch (\Throwable $e) {
            return [false, 'PayFast validate error: ' . $e->getMessage()];
        }
        return [true, 'OK'];
    }

    // ---------------------------------------------------------------- Ozow

    public const OZOW_URL = 'https://pay.ozow.com';

    public static function ozowFields(array $invoice, array $customer, float $amount): array
    {
        $token = $invoice['public_token'];
        $data = [
            'SiteCode'             => Settings::get('ozow_site_code'),
            'CountryCode'          => 'ZA',
            'CurrencyCode'         => 'ZAR',
            'Amount'               => number_format($amount, 2, '.', ''),
            'TransactionReference' => $invoice['number'] . '-' . time(),
            'BankReference'        => mb_substr(preg_replace('/[^A-Za-z0-9 -]/', '', $invoice['number']), 0, 20),
            'Optional1'            => (string) $invoice['id'],
            'Customer'             => mb_substr(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: (string) $customer['company_name'], 0, 100),
            'CancelUrl'            => site_url("pay/{$token}?cancelled=1"),
            'ErrorUrl'             => site_url("pay/{$token}?error=1"),
            'SuccessUrl'           => site_url("pay/{$token}/success"),
            'NotifyUrl'            => site_url('webhooks/ozow'),
            'IsTest'               => (int) Settings::get('ozow_test_mode', 1) === 1 ? 'true' : 'false',
        ];
        $data['HashCheck'] = self::ozowHash($data);
        return $data;
    }

    public static function ozowHash(array $orderedValues): string
    {
        $str = implode('', array_map(static fn ($v) => (string) $v, $orderedValues)) . Settings::get('ozow_private_key');
        return hash('sha512', strtolower($str));
    }

    /** Validate the hash on an Ozow notification / redirect. */
    public static function ozowValidateResponse(array $p): bool
    {
        $keys = ['SiteCode', 'TransactionId', 'TransactionReference', 'Amount', 'Status', 'Optional1', 'Optional2', 'Optional3', 'Optional4', 'Optional5', 'CurrencyCode', 'IsTest', 'StatusMessage'];
        $vals = array_map(static fn ($k) => $p[$k] ?? '', $keys);
        return hash_equals(self::ozowHash($vals), strtolower((string) ($p['Hash'] ?? '')));
    }

    /** Optional double-check with Ozow's API (requires API key). */
    public static function ozowConfirm(string $reference): ?array
    {
        $apiKey = Settings::get('ozow_api_key');
        if (!$apiKey) {
            return null;
        }
        try {
            $res = service('curlrequest', ['timeout' => 15], null, false)->get('https://api.ozow.com/GetTransactionByReference', [
                'query' => ['siteCode' => Settings::get('ozow_site_code'), 'transactionReference' => $reference, 'isTest' => (int) Settings::get('ozow_test_mode', 1) === 1 ? 'true' : 'false'],
                'headers' => ['ApiKey' => $apiKey, 'Accept' => 'application/json'], 'http_errors' => false,
            ]);
            $json = json_decode($res->getBody(), true);
            return is_array($json) ? ($json[0] ?? $json) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
