<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use Firebase\JWT\JWT;

/**
 * Mobile app authentication (Phase 3 foundation). POST /api/v1/auth/login {email, password}
 */
class Auth extends BaseController
{
    public function login()
    {
        try {
            $in = $this->request->getJSON(true) ?: $this->request->getPost();
        } catch (\Throwable $e) {
            $in = $this->request->getPost();
        }
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if (service('throttler')->check(md5('api' . $this->request->getIPAddress() . $email), 10, 600) === false) {
            return $this->json(['error' => 'Too many attempts. Try again in a few minutes.'], 429);
        }
        $user = $this->db->table('users')->where(['email' => $email, 'is_active' => 1, 'deleted_at' => null])->get()->getRowArray();
        if (!$user || !password_verify((string) ($in['password'] ?? ''), $user['password_hash'])) {
            return $this->json(['error' => 'Incorrect email or password.'], 401);
        }
        if ((int) ($user['totp_enabled'] ?? 0) === 1) {
            $code = (string) ($in['code'] ?? '');
            if ($code === '') {
                return $this->json(['error' => 'Enter the 6-digit code from your authenticator app.', 'two_factor_required' => true], 401);
            }
            if (!\App\Libraries\Totp::verify((string) $user['totp_secret'], $code)) {
                return $this->json(['error' => 'Incorrect security code.', 'two_factor_required' => true], 401);
            }
        }
        $now = time();
        $token = JWT::encode(['iss' => base_url(), 'sub' => (int) $user['id'], 'role' => $user['role'], 'iat' => $now, 'exp' => $now + 60 * 60 * 24 * 30], (string) env('jwt.secret'), 'HS256');
        $this->db->table('users')->where('id', $user['id'])->update(['last_login_at' => date('Y-m-d H:i:s')]);
        return $this->json(['token' => $token, 'user' => $this->publicUser($user)]);
    }

    public function me()
    {
        return $this->json(['user' => $this->publicUser(auth_user())]);
    }

    private function publicUser(array $u): array
    {
        return [
            'id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'email' => $u['email'],
            'role' => $u['role'], 'customer_id' => $u['customer_id'] ? (int) $u['customer_id'] : null, 'branch_id' => $u['branch_id'] ? (int) $u['branch_id'] : null,
        ];
    }
}
