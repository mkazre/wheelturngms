<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a logged-in staff session. Usage: 'filter' => 'auth' or 'auth:invoices'
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (!$session->get('user_id')) {
            $session->set('redirect_after_login', current_url());
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }
        // Re-validate the account periodically so deactivated users are kicked out.
        if ((int) $session->get('auth_checked_at') < time() - 300) {
            $user = db_connect()->table('users')->select('is_active, role')->where(['id' => $session->get('user_id'), 'deleted_at' => null])->get()->getRowArray();
            if (!$user || (int) $user['is_active'] !== 1) {
                $session->destroy();
                return redirect()->to(site_url('login'))->with('error', 'Your account is no longer active.');
            }
            $session->set(['role' => $user['role'], 'auth_checked_at' => time()]);
        }
        if ($session->get('role') === 'customer' && !in_array('portal', (array) $arguments, true)) {
            return redirect()->to(site_url('portal'));
        }
        foreach ((array) $arguments as $module) {
            if (!can($module)) {
                if ($request->isAJAX()) {
                    return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
                }
                return redirect()->to(site_url(can('dashboard') ? 'dashboard' : 'workshop'))->with('error', 'You do not have access to that area.');
            }
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
