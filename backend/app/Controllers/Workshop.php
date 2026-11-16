<?php

namespace App\Controllers;

class Workshop extends BaseController
{
    public const COLUMNS = ['booked', 'checked_in', 'in_progress', 'awaiting_approval', 'awaiting_parts', 'quality_check', 'completed'];

    public function index()
    {
        return view('workshop/index', ['columns' => $this->columns()]);
    }

    /** Full-screen TV display for the workshop floor / reception (auto-refreshes). */
    public function tv()
    {
        $today = $this->db->table('job_cards')->where('deleted_at', null)->where('completed_at >=', date('Y-m-d 00:00:00'))->countAllResults();
        return view('workshop/tv', ['columns' => $this->columns(true), 'completedToday' => $today]);
    }

    private function columns(bool $all = false): array
    {
        $b = $this->db->table('job_cards j')
            ->select('j.id, j.number, j.status, j.priority, j.job_type, j.bay, j.promised_at, j.booked_for, j.total, j.customer_complaint, v.registration, v.make, v.model, c.first_name, c.last_name, c.company_name, c.type')
            ->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->where('j.deleted_at', null)->whereIn('j.status', self::COLUMNS)
            ->orderBy("CASE j.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END", '', false)
            ->orderBy('j.promised_at');
        if (!$all && (session('role') === 'mechanic' || $this->request->getGet('mine'))) {
            $b->where('j.id IN (SELECT job_card_id FROM job_card_mechanics WHERE user_id = ' . (int) session('user_id') . ')', null, false);
        }
        $jobs = $b->get()->getResultArray();

        $ids = array_column($jobs, 'id') ?: [0];
        $mechs = [];
        foreach ($this->db->table('job_card_mechanics jm')->select('jm.job_card_id, u.first_name, u.last_name')->join('users u', 'u.id = jm.user_id')->whereIn('jm.job_card_id', $ids)->get()->getResultArray() as $m) {
            $mechs[$m['job_card_id']][] = initials($m['first_name'] . ' ' . $m['last_name']);
        }
        $running = array_column($this->db->table('time_logs')->select('job_card_id')->where('ended_at', null)->whereIn('job_card_id', $ids)->get()->getResultArray(), 'job_card_id', 'job_card_id');

        $columns = array_fill_keys(self::COLUMNS, []);
        foreach ($jobs as $j) {
            $j['mechs'] = $mechs[$j['id']] ?? [];
            $j['running'] = isset($running[$j['id']]);
            $columns[$j['status']][] = $j;
        }
        return $columns;
    }
}
