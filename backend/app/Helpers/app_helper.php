<?php

use App\Libraries\Settings;

if (!function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('money')) {
    function money($amount, bool $symbol = true): string
    {
        $n = number_format((float) $amount, 2, '.', ' ');
        return $symbol ? Settings::get('currency_symbol', 'R') . ' ' . $n : $n;
    }
}

if (!function_exists('money_short')) {
    function money_short($amount): string
    {
        $a = (float) $amount;
        $s = Settings::get('currency_symbol', 'R');
        if (abs($a) >= 1_000_000) {
            return $s . number_format($a / 1_000_000, 2) . 'm';
        }
        if (abs($a) >= 10_000) {
            return $s . number_format($a / 1000, 1) . 'k';
        }
        return money($a);
    }
}

if (!function_exists('fdate')) {
    function fdate(?string $date, string $format = 'd M Y'): string
    {
        return $date ? date($format, strtotime($date)) : '—';
    }
}

if (!function_exists('fdatetime')) {
    function fdatetime(?string $date): string
    {
        return $date ? date('d M Y, H:i', strtotime($date)) : '—';
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        $d = time() - strtotime($date);
        if ($d < 60) {
            return 'just now';
        }
        foreach ([86400 * 30 => 'month', 86400 * 7 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'min'] as $secs => $unit) {
            if ($d >= $secs) {
                $n = (int) floor($d / $secs);
                return $n . ' ' . $unit . ($n > 1 ? 's' : '') . ' ago';
            }
        }
        return fdate($date);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(?string $key = null)
    {
        static $user = false;
        if ($user === false) {
            $id   = session('user_id');
            $user = $id ? db_connect()->table('users')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : null;
        }
        return $key ? ($user[$key] ?? null) : $user;
    }
}

if (!function_exists('can')) {
    function can(string $module): bool
    {
        $role = session('role');
        if (!$role) {
            return false;
        }
        $perms = config('Permissions')->roles[$role] ?? [];
        return in_array('*', $perms, true) || in_array($module, $perms, true);
    }
}

if (!function_exists('customer_name')) {
    function customer_name(?array $c): string
    {
        if (!$c) {
            return '—';
        }
        if (($c['type'] ?? '') === 'business' && !empty($c['company_name'])) {
            return $c['company_name'];
        }
        return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['company_name'] ?? '—');
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }
}

if (!function_exists('job_statuses')) {
    function job_statuses(): array
    {
        return [
            'booked'            => ['label' => 'Booked',            'badge' => 'badge-gray',   'hex' => '#94a3b8'],
            'checked_in'        => ['label' => 'Checked in',        'badge' => 'badge-blue',   'hex' => '#3b82f6'],
            'in_progress'       => ['label' => 'In progress',       'badge' => 'badge-purple', 'hex' => '#8b5cf6'],
            'awaiting_approval' => ['label' => 'Awaiting approval', 'badge' => 'badge-amber',  'hex' => '#f59e0b'],
            'awaiting_parts'    => ['label' => 'Awaiting parts',    'badge' => 'badge-amber',  'hex' => '#f97316'],
            'quality_check'     => ['label' => 'Quality check',     'badge' => 'badge-navy',   'hex' => '#353d96'],
            'completed'         => ['label' => 'Ready',             'badge' => 'badge-green',  'hex' => '#10b981'],
            'invoiced'          => ['label' => 'Invoiced',          'badge' => 'badge-green',  'hex' => '#059669'],
            'collected'         => ['label' => 'Collected',         'badge' => 'badge-gray',   'hex' => '#64748b'],
            'cancelled'         => ['label' => 'Cancelled',         'badge' => 'badge-red',    'hex' => '#ef4444'],
        ];
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status, string $kind = 'job'): string
    {
        $maps = [
            'job'     => array_map(static fn ($s) => [$s['label'], $s['badge']], job_statuses()),
            'invoice' => ['draft' => ['Draft', 'badge-gray'], 'unpaid' => ['Unpaid', 'badge-amber'], 'partial' => ['Part paid', 'badge-blue'], 'paid' => ['Paid', 'badge-green'], 'void' => ['Void', 'badge-red'], 'overdue' => ['Overdue', 'badge-red']],
            'quote'   => ['draft' => ['Draft', 'badge-gray'], 'sent' => ['Sent', 'badge-blue'], 'accepted' => ['Accepted', 'badge-green'], 'declined' => ['Declined', 'badge-red'], 'expired' => ['Expired', 'badge-amber'], 'converted' => ['Converted', 'badge-purple']],
            'payment' => ['completed' => ['Completed', 'badge-green'], 'pending' => ['Pending', 'badge-amber'], 'failed' => ['Failed', 'badge-red']],
        ];
        [$label, $cls] = $maps[$kind][$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-gray'];
        return '<span class="' . $cls . '"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>' . esc($label) . '</span>';
    }
}

if (!function_exists('invoice_display_status')) {
    function invoice_display_status(array $inv): string
    {
        if (in_array($inv['status'], ['unpaid', 'partial'], true) && $inv['due_date'] && $inv['due_date'] < date('Y-m-d')) {
            return 'overdue';
        }
        return $inv['status'];
    }
}

if (!function_exists('nav_active')) {
    function nav_active(string $prefix): string
    {
        $path = trim(service('request')->getUri()->getPath(), '/');
        $prefix = trim($prefix, '/');
        return ($prefix === '' ? $path === '' || $path === 'dashboard' : str_starts_with($path, $prefix)) ? 'active' : '';
    }
}

if (!function_exists('icon')) {
    /** Inline Lucide-style SVG icons (no external dependency). */
    function icon(string $name, string $class = 'h-5 w-5'): string
    {
        $p = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'board'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18"/>',
            'wrench'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            'file'      => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
            'quote'     => '<path d="M16 3h5v5M21 3l-7 7M11 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6"/>',
            'invoice'   => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 17.5v-11"/>',
            'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'car'       => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
            'box'       => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
            'truck'     => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62L18.3 9.38a1 1 0 0 0-.78-.38H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'receipt'   => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
            'chart'     => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
            'pie'       => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'book'      => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
            'settings'  => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'plus'      => '<path d="M5 12h14M12 5v14"/>',
            'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
            'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
            'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'printer'   => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
            'edit'      => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            'trash'     => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
            'check'     => '<path d="M20 6 9 17l-5-5"/>',
            'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
            'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'play'      => '<polygon points="6 3 20 12 6 21 6 3"/>',
            'stop'      => '<rect x="5" y="5" width="14" height="14" rx="2"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
            'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
            'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
            'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'alert'     => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
            'paperclip' => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'arrow-up'  => '<path d="m5 12 7-7 7 7M12 19V5"/>',
            'arrow-down'=> '<path d="M12 5v14M19 12l-7 7-7-7"/>',
            'arrow-right'=> '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 14l2 2 4-4"/>',
            'wallet'    => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
            'building'  => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
            'shield'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'zap'       => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
            'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'eye'       => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'copy'      => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'refresh'   => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5"/>',
        ];
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '" aria-hidden="true">' . ($p[$name] ?? $p['file']) . '</svg>';
    }
}

if (!function_exists('wa_link')) {
    /** WhatsApp click-to-chat URL with a pre-filled message, or null if no usable number. */
    function wa_link(?string $phone, string $text): ?string
    {
        return \App\Libraries\Notify::whatsappLink($phone, $text);
    }
}

if (!function_exists('wa_button')) {
    function wa_button(?string $phone, string $text, string $label = 'WhatsApp', string $class = 'btn-sm'): string
    {
        $url = wa_link($phone, $text);
        if (!$url) {
            return '';
        }
        return '<a href="' . esc($url, 'attr') . '" target="_blank" rel="noopener" class="btn ' . $class . ' bg-[#25D366] text-white hover:brightness-95">'
            . '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg> '
            . esc($label) . '</a>';
    }
}
