<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;

/**
 * File attachments (payment slips, receipts, vehicle photos, documents) for any record.
 * Files live outside the web root in writable/uploads and are streamed through a controller.
 */
class Attachments
{
    public const ENTITIES   = ['customer', 'vehicle', 'job_card', 'quote', 'invoice', 'payment', 'expense', 'supplier', 'inspection', 'bill', 'purchase_order', 'credit_note', 'journal', 'vat_return'];
    public const CATEGORIES = ['slip' => 'Payment slip / POP', 'receipt' => 'Receipt', 'photo' => 'Photo', 'document' => 'Document', 'licence' => 'Licence / ID', 'other' => 'Other'];
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'mp4', 'mov', 'webm', 'm4v', '3gp'];
    public const MAX_BYTES  = 15 * 1024 * 1024;
    public const MAX_VIDEO_BYTES = 60 * 1024 * 1024;
    public const VIDEO_EXT = ['mp4', 'mov', 'webm', 'm4v', '3gp'];

    public static function store(UploadedFile $file, string $entityType, int $entityId, string $category = 'document', ?string $note = null): int
    {
        if (!in_array($entityType, self::ENTITIES, true)) {
            throw new RuntimeException('Unsupported record type.');
        }
        if (!$file->isValid() || $file->hasMoved()) {
            throw new RuntimeException($file->getErrorString());
        }
        $ext = strtolower($file->guessExtension() ?: $file->getClientExtension());
        if (!in_array($ext, self::EXTENSIONS, true) || !in_array(strtolower($file->getClientExtension()), self::EXTENSIONS, true)) {
            throw new RuntimeException('File type not allowed. Use images, videos, PDF, Word, Excel or CSV.');
        }
        $isVideo = in_array($ext, self::VIDEO_EXT, true) || in_array(strtolower($file->getClientExtension()), self::VIDEO_EXT, true);
        if ($file->getSize() > ($isVideo ? self::MAX_VIDEO_BYTES : self::MAX_BYTES)) {
            throw new RuntimeException($isVideo ? 'Video is larger than 60 MB. Record a shorter clip.' : 'File is larger than 15 MB.');
        }
        $category = array_key_exists($category, self::CATEGORIES) ? $category : 'other';
        $dir = $entityType . '/' . date('Y/m');
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $mime = $file->getMimeType();
        $size = $file->getSize();
        $orig = $file->getClientName();
        $file->move(WRITEPATH . 'uploads/' . $dir, $name);

        $db = Database::connect();
        $db->table('attachments')->insert([
            'entity_type' => $entityType, 'entity_id' => $entityId,
            'category' => $category,
            'original_name' => mb_substr($orig, 0, 255), 'file_path' => $dir . '/' . $name,
            'mime' => $mime, 'size' => $size, 'note' => $note,
            'uploaded_by' => session('user_id'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertID();
        Activity::log($entityType, $entityId, 'attachment', 'Uploaded ' . self::CATEGORIES[$category] . ': ' . $orig);
        return $id;
    }

    public static function for(string $entityType, int $entityId): array
    {
        return Database::connect()->table('attachments a')->select('a.*, u.first_name, u.last_name')
            ->join('users u', 'u.id = a.uploaded_by', 'left')
            ->where(['a.entity_type' => $entityType, 'a.entity_id' => $entityId])->orderBy('a.id', 'DESC')->get()->getResultArray();
    }

    public static function path(array $a): string
    {
        return WRITEPATH . 'uploads/' . $a['file_path'];
    }

    public static function isImage(array $a): bool
    {
        return str_starts_with((string) $a['mime'], 'image/');
    }

    public static function counts(string $entityType, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return array_column(Database::connect()->table('attachments')->select('entity_id, COUNT(*) n')
            ->where('entity_type', $entityType)->whereIn('entity_id', $ids)->groupBy('entity_id')->get()->getResultArray(), 'n', 'entity_id');
    }
}
