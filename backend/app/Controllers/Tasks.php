<?php

namespace App\Controllers;

use App\Libraries\Notify;
use App\Libraries\Settings as S;

/** Staff tasks / reminders, and the in-app notification centre. */
class Tasks extends BaseController
{
    public function index()
    {
        $scope = $this->request->getGet('scope') ?: 'mine';
        $b = $this->db->table('tasks t')->select('t.*, a.first_name assignee, c.first_name creator')
            ->join('users a', 'a.id = t.assigned_to', 'left')->join('users c', 'c.id = t.created_by', 'left');
        if ($scope === 'mine') {
            $b->where('t.assigned_to', session('user_id'));
        }
        $b->where('t.status', $this->request->getGet('done') ? 'done' : 'open')->orderBy('t.due_at IS NULL', '', false)->orderBy('t.due_at');
        return view('tasks/index', [
            'rows' => $b->get()->getResultArray(), 'scope' => $scope,
            'staff' => $this->db->table('users')->select('id, first_name, last_name')->where(['is_active' => 1, 'deleted_at' => null])->where('role !=', 'customer')->orderBy('first_name')->get()->getResultArray(),
        ]);
    }

    public function save()
    {
        $title = trim((string) $this->request->getPost('title'));
        if ($title === '') {
            return redirect()->back()->with('error', 'Task title is required.');
        }
        $assignee = (int) $this->request->getPost('assigned_to') ?: (int) session('user_id');
        $this->db->table('tasks')->insert([
            'title' => mb_substr($title, 0, 255), 'notes' => $this->input('notes'), 'due_at' => $this->input('due_at') ? date('Y-m-d H:i:s', strtotime($this->input('due_at'))) : null,
            'assigned_to' => $assignee, 'entity_type' => $this->input('entity_type'), 'entity_id' => (int) $this->input('entity_id', 0) ?: null,
            'status' => 'open', 'priority' => $this->input('priority', 'normal'), 'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now(),
        ]);
        if ($assignee !== (int) session('user_id')) {
            Notify::staff('New task: ' . $title, 'From ' . auth_user('first_name') . ($this->input('due_at') ? ' · due ' . fdatetime($this->input('due_at')) : ''), 'tasks', 'clipboard', $assignee);
        }
        return redirect()->back()->with('success', 'Task added.');
    }

    public function complete(int $id)
    {
        $t = $this->db->table('tasks')->where('id', $id)->get()->getRowArray();
        $done = $t && $t['status'] === 'open';
        $this->db->table('tasks')->where('id', $id)->update(['status' => $done ? 'done' : 'open', 'completed_at' => $done ? $this->now() : null, 'updated_at' => $this->now()]);
        if ($this->request->isAJAX()) {
            return $this->json(['ok' => true]);
        }
        return redirect()->back()->with('success', $done ? 'Task completed.' : 'Task re-opened.');
    }

    // ------------------------------------------------------------ notifications

    public function notifications()
    {
        $uid = (int) session('user_id');
        $rows = Notify::forUser($uid, (string) session('role'));
        $seen = (string) S::get('notif_seen_' . $uid, '1970-01-01 00:00:00');
        S::set('notif_seen_' . $uid, date('Y-m-d H:i:s'), 'system');
        $overdue = $this->db->table('tasks')->where(['assigned_to' => $uid, 'status' => 'open'])->where('due_at <', $this->now())->countAllResults();
        return $this->json(['items' => array_map(static fn ($n) => $n + ['unread' => $n['created_at'] > $seen, 'ago' => time_ago($n['created_at']), 'link' => $n['url'] ? site_url($n['url']) : null], $rows), 'overdueTasks' => $overdue]);
    }
}
