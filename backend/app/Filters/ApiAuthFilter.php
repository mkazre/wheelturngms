<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Bearer-token (JWT) auth for the Expo mobile app API. Phase 3 builds the full API on top of this.
 */
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Missing token']);
        }
        try {
            $payload = JWT::decode($m[1], new Key((string) env('jwt.secret'), 'HS256'));
        } catch (\Throwable $e) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Invalid or expired token']);
        }
        $user = db_connect()->table('users')->where(['id' => $payload->sub, 'is_active' => 1, 'deleted_at' => null])->get()->getRowArray();
        if (!$user) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Account inactive']);
        }
        // Make the API user available to permission helpers for this request only.
        session()->set(['user_id' => (int) $user['id'], 'role' => $user['role'], 'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null]);
        foreach ((array) $arguments as $module) {
            if (!can($module)) {
                return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
            }
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
