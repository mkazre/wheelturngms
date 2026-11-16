<?php

namespace App\Controllers;

use App\Libraries\Activity;

class Users extends BaseController
{
    public function index()
    {
        $rows = $this->db->table('users u')->select('u.*, b.name branch_name')->join('branches b', 'b.id = u.branch_id', 'left')
            ->where('u.deleted_at', null)->where('u.role !=', 'customer')->orderBy('u.is_active', 'DESC')->orderBy('u.first_name')->get()->getResultArray();
        return view('users/index', ['rows' => $rows, 'labels' => config('Permissions')->labels]);
    }

    public function form(?int $id = null)
    {
        $u = $id ? $this->db->table('users')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : ['role' => 'mechanic', 'is_active' => 1, 'branch_id' => session('branch_id')];
        if ($id && !$u) {
            return $this->notFound('User');
        }
        return view('users/form', ['u' => $u, 'labels' => array_diff_key(config('Permissions')->labels, ['customer' => 1]), 'branches' => $this->db->table('branches')->where('is_active', 1)->get()->getResultArray()]);
    }

    public function save(?int $id = null)
    {
        $rules = ['first_name' => 'required', 'email' => 'required|valid_email', 'role' => 'required|in_list[admin,manager,advisor,mechanic,accountant]'];
        if (!$id || $this->request->getPost('password')) {
            $rules['password'] = 'required|min_length[8]';
        }
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        // Only admins can create or promote admins
        if ($this->request->getPost('role') === 'admin' && session('role') !== 'admin') {
            return redirect()->back()->withInput()->with('error', 'Only administrators can assign the Administrator role.');
        }
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $dupe = $this->db->table('users')->where(['email' => $email, 'deleted_at' => null]);
        if ($id) {
            $dupe->where('id !=', $id);
        }
        if ($dupe->countAllResults()) {
            return redirect()->back()->withInput()->with('error', 'That email is already in use.');
        }
        $data = $this->only(['first_name', 'last_name', 'phone', 'role', 'job_title', 'branch_id']) + [
            'email' => $email, 'hourly_rate' => (float) $this->input('hourly_rate', 0), 'cost_rate' => (float) $this->input('cost_rate', 0),
            'commission_percent' => (float) $this->input('commission_percent', 0), 'monthly_target' => (float) $this->input('monthly_target', 0),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now(),
        ];
        if ($pw = $this->request->getPost('password')) {
            $data['password_hash'] = password_hash((string) $pw, PASSWORD_DEFAULT);
        }
        if ($id && $id === (int) session('user_id') && (!$data['is_active'] || $data['role'] !== session('role'))) {
            return redirect()->back()->with('error', 'You cannot deactivate or change the role of your own account.');
        }
        if ($id) {
            $this->db->table('users')->where('id', $id)->update($data);
        } else {
            $this->db->table('users')->insert($data + ['created_at' => $this->now()]);
            $id = (int) $this->db->insertID();
        }
        Activity::log('user', $id, 'saved', 'Saved team member ' . $data['first_name']);
        return redirect()->to(site_url('users'))->with('success', 'Team member saved.');
    }

    public function delete(int $id)
    {
        if ($id === (int) session('user_id')) {
            return redirect()->back()->with('error', 'You cannot remove yourself.');
        }
        $this->db->table('users')->where('id', $id)->update(['deleted_at' => $this->now(), 'is_active' => 0]);
        return redirect()->to(site_url('users'))->with('success', 'Team member removed.');
    }
}
