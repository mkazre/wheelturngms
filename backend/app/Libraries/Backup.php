<?php

namespace App\Libraries;

use Config\Database;

/**
 * Database backups (pure PHP, no shell access needed on cPanel). Gzipped SQL in writable/backups,
 * keeps the newest N files. Uploaded files (writable/uploads) are backed up by cPanel's own backups.
 */
class Backup
{
    public static function dir(): string
    {
        $d = WRITEPATH . 'backups/';
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
            file_put_contents($d . 'index.html', '');
            file_put_contents($d . '.htaccess', "Require all denied\n");
        }
        return $d;
    }

    public static function run(bool $force = false): ?string
    {
        if (!$force && Settings::get('backup_last') === date('Y-m-d')) {
            return null;
        }
        $db = Database::connect();
        $file = self::dir() . 'wheelsturn-' . date('Ymd-His') . '.sql.gz';
        $gz = gzopen($file, 'w6');
        gzwrite($gz, "-- Wheelsturn GMS backup " . date('c') . " (" . $db->DBDriver . ")\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($db->listTables() as $table) {
            if ($db->DBDriver === 'MySQLi') {
                $create = $db->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray();
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n" . ($create['Create Table'] ?? '') . ";\n\n");
            }
            $offset = 0;
            while (true) {
                $rows = $db->table($table)->limit(500, $offset)->get()->getResultArray();
                if (!$rows) {
                    break;
                }
                foreach ($rows as $r) {
                    $vals = array_map(static fn ($v) => $v === null ? 'NULL' : $db->escape((string) $v), array_values($r));
                    gzwrite($gz, 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($r)) . '`) VALUES (' . implode(',', $vals) . ");\n");
                }
                $offset += 500;
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        Settings::set('backup_last', date('Y-m-d'), 'system');
        self::prune((int) Settings::get('backup_keep', 14));
        return $file;
    }

    public static function list(): array
    {
        $files = glob(self::dir() . '*.sql.gz') ?: [];
        rsort($files);
        return array_map(static fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], $files);
    }

    private static function prune(int $keep): void
    {
        $files = glob(self::dir() . '*.sql.gz') ?: [];
        rsort($files);
        foreach (array_slice($files, max(1, $keep)) as $old) {
            @unlink($old);
        }
    }
}
