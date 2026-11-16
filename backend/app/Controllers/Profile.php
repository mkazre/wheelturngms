<?php

namespace App\Controllers;

class Profile extends BaseController
{
    public function index()
    {
        return view('profile/index', ['u' => auth_user()]);
    }

    /** Start or confirm two-factor setup. */
    public function twoFactor()
    {
        $u = auth_user();
        $action = (string) $this->request->getPost('action');
        if ($action === 'start') {
            $secret = \App\Libraries\Totp::secret();
            session()->set('totp_setup', $secret);
            return redirect()->to(site_url('profile#security'));
        }
        if ($action === 'confirm') {
            $secret = (string) session('totp_setup');
            if (!$secret || !\App\Libraries\Totp::verify($secret, (string) $this->request->getPost('code'))) {
                return redirect()->to(site_url('profile#security'))->with('error', 'Code not accepted. Scan the QR code again and enter the current code.');
            }
            $this->db->table('users')->where('id', $u['id'])->update(['totp_secret' => $secret, 'totp_enabled' => 1]);
            session()->remove('totp_setup');
            \App\Libraries\Activity::log('user', (int) $u['id'], '2fa_on', 'Two-factor authentication enabled');
            return redirect()->to(site_url('profile'))->with('success', 'Two-factor authentication is on. You will need your phone to sign in.');
        }
        if ($action === 'disable') {
            if (!password_verify((string) $this->request->getPost('password'), $u['password_hash'])) {
                return redirect()->back()->with('error', 'Password incorrect.');
            }
            $this->db->table('users')->where('id', $u['id'])->update(['totp_secret' => null, 'totp_enabled' => 0]);
            \App\Libraries\Activity::log('user', (int) $u['id'], '2fa_off', 'Two-factor authentication disabled');
            return redirect()->back()->with('success', 'Two-factor authentication turned off.');
        }
        return redirect()->back();
    }

    public function update()
    {
        $u = auth_user();
        $data = $this->only(['first_name', 'last_name', 'phone']) + ['updated_at' => $this->now()];
        if (!$data['first_name']) {
            return redirect()->back()->with('error', 'First name is required.');
        }
        if ($new = (string) $this->request->getPost('new_password')) {
            if (!password_verify((string) $this->request->getPost('current_password'), $u['password_hash'])) {
                return redirect()->back()->with('error', 'Your current password is incorrect.');
            }
            if (strlen($new) < 8 || $new !== $this->request->getPost('confirm_password')) {
                return redirect()->back()->with('error', 'New passwords must match and be at least 8 characters.');
            }
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        $this->db->table('users')->where('id', $u['id'])->update($data);
        return redirect()->back()->with('success', 'Profile updated.');
    }
}
