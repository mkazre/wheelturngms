<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;

class Attachments extends BaseController
{
    private function find(int $id): array
    {
        $a = $this->db->table('attachments')->where('id', $id)->get()->getRowArray();
        if (!$a || !is_file(Files::path($a))) {
            $this->notFound('File');
        }
        // Customers may only see files on their own records (portal); staff see all.
        if (session('role') === 'customer') {
            $this->notFound('File');
        }
        return $a;
    }

    public function upload()
    {
        if (session('role') === 'customer') {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $type = (string) $this->request->getPost('entity_type');
        $id   = (int) $this->request->getPost('entity_id');
        $file = $this->request->getFile('file');
        try {
            if (!$file) {
                throw new \RuntimeException('No file received.');
            }
            $aid = Files::store($file, $type, $id, (string) $this->request->getPost('category'), $this->input('note'));
        } catch (\Throwable $e) {
            if ($this->request->isAJAX()) {
                return $this->json(['error' => $e->getMessage(), 'csrf' => csrf_hash()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
        if ($this->request->isAJAX()) {
            return $this->json(['id' => $aid, 'csrf' => csrf_hash()]);
        }
        return redirect()->back()->with('success', 'File uploaded.');
    }

    public function view(int $id)
    {
        $a = $this->find($id);
        return $this->response->setHeader('Content-Type', $a['mime'] ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline; filename="' . addslashes($a['original_name']) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents(Files::path($a)));
    }

    public function download(int $id)
    {
        $a = $this->find($id);
        return $this->response->download(Files::path($a), null)->setFileName($a['original_name']);
    }

    public function delete(int $id)
    {
        $a = $this->find($id);
        @unlink(Files::path($a));
        $this->db->table('attachments')->where('id', $id)->delete();
        Activity::log($a['entity_type'], (int) $a['entity_id'], 'attachment_deleted', 'Deleted file ' . $a['original_name']);
        return redirect()->back()->with('success', 'File deleted.');
    }
}
