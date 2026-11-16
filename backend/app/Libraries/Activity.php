<?php

namespace App\Libraries;

use Config\Database;

/** Audit trail of who did what. */
class Activity
{
    public static function log(?string $entityType, ?int $entityId, string $action, string $description = ''): void
    {
        try {
            Database::connect()->table('activity_logs')->insert([
                'user_id' => session('user_id'), 'entity_type' => $entityType, 'entity_id' => $entityId,
                'action' => $action, 'description' => mb_substr($description, 0, 500),
                'ip_address' => is_cli() ? 'cli' : service('request')->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Activity log failed: ' . $e->getMessage());
        }
    }

    public static function for(string $entityType, int $entityId, int $limit = 50): array
    {
        return Database::connect()->table('activity_logs a')
            ->select('a.*, u.first_name, u.last_name')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->where(['a.entity_type' => $entityType, 'a.entity_id' => $entityId])
            ->orderBy('a.id', 'DESC')->limit($limit)->get()->getResultArray();
    }
}
