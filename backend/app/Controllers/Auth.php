<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Mailer;

class Auth extends BaseController
{
    public function login()
    {
        return view('auth/login');
    }

    public function attempt()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $pass  = (string) $this->request->getPost('password');

        // Basic brute-force throttle: 8 attempts per 10 minutes per IP+email
        $throttler = service('throttler');
        if ($throttler->check(md5('login' . $this->request->getIPAddress() . $email), 8, 600) === false) {
            return redirect()->back()->withInput()->with('error', 'Too many attempts. Please wait a few minutes and try again.');
        }

        $user = $this->db->table('users')->where(['email' => $email, 'deleted_at' => null])->get()->getRowArray();
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Incorrect email or password.');
        }
        if ((int) $user['is_active'] !== 1) {
            return redirect()->back()->withInput()->with('error', 'Your account has been deactivated. Contact your manager.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->table('users')->where('id', $user['id'])->update(['password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
        }

        if ((int) ($user['totp_enabled'] ?? 0) === 1) {
            session()->regenerate(true);
            session()->set(['pending_2fa_user' => (int) $user['id'], 'pending_2fa_at' => time()]);
            return redirect()->to(site_url('login/2fa'));
        }
        return $this->completeLogin($user);
    }

    /** Second step for accounts with two-factor authentication. */
    public function twoFactor()
    {
        if (!session('pending_2fa_user') || (int) session('pending_2fa_at') < time() - 300) {
            return redirect()->to(site_url('login'))->with('error', 'Please sign in again.');
        }
        return view('auth/two_factor');
    }

    public function twoFactorVerify()
    {
        $uid = (int) session('pending_2fa_user');
        if (!$uid || (int) session('pending_2fa_at') < time() - 300) {
            return redirect()->to(site_url('login'))->with('error', 'Your sign-in timed out. Please sign in again.');
        }
        if (service('throttler')->check(md5('2fa' . $uid), 5, 300) === false) {
            return redirect()->to(site_url('login'))->with('error', 'Too many incorrect codes. Please wait 5 minutes.');
        }
        $user = $this->db->table('users')->where(['id' => $uid, 'is_active' => 1, 'deleted_at' => null])->get()->getRowArray();
        if (!$user || !\App\Libraries\Totp::verify((string) $user['totp_secret'], (string) $this->request->getPost('code'))) {
            return redirect()->back()->with('error', 'That code is not correct. Check the time on your phone and try again.');
        }
        session()->remove(['pending_2fa_user', 'pending_2fa_at']);
        return $this->completeLogin($user);
    }

    private function completeLogin(array $user)
    {
        session()->regenerate(true);
        session()->set([
            'user_id' => (int) $user['id'], 'role' => $user['role'], 'branch_id' => $user['branch_id'],
            'customer_id' => $user['customer_id'], 'auth_checked_at' => time(),
        ]);
        $this->db->table('users')->where('id', $user['id'])->update(['last_login_at' => $this->now()]);
        Activity::log('user', (int) $user['id'], 'login', 'Signed in');

        $dest = session('redirect_after_login');
        session()->remove('redirect_after_login');
        if ($user['role'] === 'customer') {
            return redirect()->to(site_url('portal'));
        }
        return redirect()->to($dest ?: site_url(can('dashboard') ? 'dashboard' : 'workshop'));
    }

    public function logout()
    {
        if (session('user_id')) {
            Activity::log('user', (int) session('user_id'), 'logout', 'Signed out');
        }
        session()->destroy();
        return redirect()->to(site_url('login'))->with('success', 'You have been signed out.');
    }

    public function forgot()
    {
        return view('auth/forgot');
    }

    public function sendReset()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $user  = $this->db->table('users')->where(['email' => $email, 'is_active' => 1, 'deleted_at' => null])->get()->getRowArray();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->db->table('users')->where('id', $user['id'])->update([
                'reset_token' => hash('sha256', $token), 'reset_expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]);
            $link = site_url('reset-password/' . $token);
            Mailer::send($email, 'Reset your password', '<p>Hi ' . esc($user['first_name']) . ',</p><p>Someone (hopefully you) asked to reset your password. This link is valid for 1 hour.</p>'
                . view('emails/_pay_button', ['url' => $link, 'label' => 'Reset password']) . '<p style="color:#64748b;font-size:12px">If you did not request this, you can ignore this email.</p>', [], 'user', (int) $user['id']);
        }
        // Same response either way to avoid account enumeration
        return redirect()->to(site_url('login'))->with('success', 'If that email exists, a reset link has been sent.');
    }

    public function reset(string $token)
    {
        if (!$this->findByToken($token)) {
            return redirect()->to(site_url('forgot-password'))->with('error', 'That reset link is invalid or has expired.');
        }
        return view('auth/reset', ['token' => $token]);
    }

    public function doReset(string $token)
    {
        $user = $this->findByToken($token);
        if (!$user) {
            return redirect()->to(site_url('forgot-password'))->with('error', 'That reset link is invalid or has expired.');
        }
        $pass = (string) $this->request->getPost('password');
        if (strlen($pass) < 8 || $pass !== $this->request->getPost('password_confirm')) {
            return redirect()->back()->with('error', 'Passwords must match and be at least 8 characters.');
        }
        $this->db->table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'reset_token' => null, 'reset_expires_at' => null,
        ]);
        return redirect()->to(site_url('login'))->with('success', 'Password updated. Please sign in.');
    }

    private function findByToken(string $token): ?array
    {
        return $this->db->table('users')->where('reset_token', hash('sha256', $token))
            ->where('reset_expires_at >=', $this->now())->where('deleted_at', null)->get()->getRowArray();
    }
}
