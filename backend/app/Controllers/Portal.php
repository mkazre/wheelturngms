<?php

namespace App\Controllers;

/** Customer self-service portal (web). The Expo app (Phase 3) uses the same data via the API. */
class Portal extends BaseController
{
    public function index()
    {
        if (session('role') !== 'customer') {
            return redirect()->to(site_url('dashboard'))->with('info', 'The customer portal is for customers. Use "Customer view" on a job card to see what they see.');
        }
        $cid = (int) auth_user('customer_id');
        $c = $this->db->table('customers')->where('id', $cid)->get()->getRowArray();
        if (!$c) {
            session()->destroy();
            return redirect()->to(site_url('login'))->with('error', 'Your customer profile could not be found.');
        }
        return view('portal/index', [
            'c' => $c,
            'vehicles' => $this->db->table('vehicles')->where(['customer_id' => $cid, 'deleted_at' => null])->get()->getResultArray(),
            'jobs' => $this->db->table('job_cards j')->select('j.number, j.status, j.created_at, j.promised_at, j.customer_complaint, v.registration, v.make, v.model')
                ->join('vehicles v', 'v.id = j.vehicle_id')->where(['j.customer_id' => $cid, 'j.deleted_at' => null])->orderBy('j.id', 'DESC')->limit(20)->get()->getResultArray(),
            'invoices' => $this->db->table('invoices')->where(['customer_id' => $cid, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->orderBy('id', 'DESC')->limit(30)->get()->getResultArray(),
            'quotes' => $this->db->table('quotes')->where(['customer_id' => $cid, 'deleted_at' => null])->whereIn('status', ['sent', 'accepted', 'draft'])->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
        ]);
    }
}
