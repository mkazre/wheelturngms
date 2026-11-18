<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Shared helpers for the mobile JSON API. ApiAuthFilter has already authenticated the JWT and set the
 * user id + role, so can() and auth_user() work exactly as on the web.
 */
abstract class BaseApi extends BaseController
{
    protected function ok($data = [], int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    protected function fail(string $message, int $status = 422, array $extra = [])
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => $message] + $extra);
    }

    /** Request body as array (JSON or form/multipart). */
    protected function body(): array
    {
        // Multipart (photo/slip uploads) and form posts are not JSON: read them as POST fields.
        if (str_contains(strtolower($this->request->getHeaderLine('Content-Type')), 'application/json')) {
            try {
                $json = $this->request->getJSON(true);
            } catch (\Throwable $e) {
                $json = null;
            }
            if (is_array($json)) {
                return $json;
            }
        }
        return (array) $this->request->getPost();
    }

    protected function val(array $b, string $k, $default = null)
    {
        $v = $b[$k] ?? null;
        if (is_string($v)) {
            $v = trim($v);
        }
        return ($v === '' || $v === null) ? $default : $v;
    }

    protected function uid(): int
    {
        return (int) session('user_id');
    }

    protected function role(): string
    {
        return (string) session('role');
    }

    protected function isMechanic(): bool
    {
        return $this->role() === 'mechanic';
    }

    protected function deny(string $module)
    {
        return can($module) ? null : $this->fail('You do not have access to this.', 403);
    }

    /** Mechanics may only open job cards assigned to them. */
    protected function canSeeJob(int $jobId): bool
    {
        if (!$this->isMechanic()) {
            return can('job_cards');
        }
        return $this->db->table('job_card_mechanics')->where(['job_card_id' => $jobId, 'user_id' => $this->uid()])->countAllResults() > 0;
    }

    protected function vehicleLabel(?array $v): string
    {
        return $v ? trim(($v['year'] ?? '') . ' ' . ($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) : '';
    }

    protected function fileUrl(int $id): string
    {
        return site_url('api/v1/files/' . $id);
    }
}
