<?php

namespace App\Libraries;

use Config\Database;

/**
 * Sequential, gap-resistant document numbers (SARS requires unique sequential tax invoice numbers).
 */
class Numbering
{
    public static function next(string $type): string
    {
        $db = Database::connect();
        $key = 'next_' . $type;

        $db->transStart();
        $row = $db->table('settings')->where('key', $key)->get()->getRowArray();
        $n   = $row ? (int) $row['value'] : 1001;
        if ($row) {
            $db->table('settings')->where('key', $key)->update(['value' => (string) ($n + 1)]);
        } else {
            $db->table('settings')->insert(['key' => $key, 'value' => (string) ($n + 1), 'group' => 'documents']);
        }
        $db->transComplete();
        Settings::flush();

        return Settings::get('prefix_' . $type, strtoupper(substr($type, 0, 3)) . '-') . $n;
    }
}
