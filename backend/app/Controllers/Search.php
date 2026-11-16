<?php

namespace App\Controllers;

/** Global search across customers, vehicles, job cards, invoices and quotes. */
class Search extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $res = ['customers' => [], 'vehicles' => [], 'jobs' => [], 'invoices' => [], 'quotes' => []];
        if (mb_strlen($q) >= 2) {
            if (can('customers')) {
                $res['customers'] = $this->db->table('customers')->where('deleted_at', null)->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('company_name', $q)->orLike('phone', $q)->orLike('email', $q)->orLike('code', $q)->groupEnd()->limit(10)->get()->getResultArray();
            }
            if (can('vehicles')) {
                $res['vehicles'] = $this->db->table('vehicles')->where('deleted_at', null)->groupStart()->like('registration', $q)->orLike('vin', $q)->orLike('make', $q)->orLike('model', $q)->groupEnd()->limit(10)->get()->getResultArray();
            }
            if (can('job_cards')) {
                $res['jobs'] = $this->db->table('job_cards j')->select('j.id, j.number, j.status, v.registration, v.make, v.model')->join('vehicles v', 'v.id = j.vehicle_id')
                    ->where('j.deleted_at', null)->groupStart()->like('j.number', $q)->orLike('v.registration', $q)->orLike('j.customer_complaint', $q)->groupEnd()->orderBy('j.id', 'DESC')->limit(10)->get()->getResultArray();
            }
            if (can('invoices')) {
                $res['invoices'] = $this->db->table('invoices')->where('deleted_at', null)->groupStart()->like('number', $q)->orLike('order_number', $q)->groupEnd()->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
            }
            if (can('quotes')) {
                $res['quotes'] = $this->db->table('quotes')->where('deleted_at', null)->like('number', $q)->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
            }
        }
        // Single exact hit → jump straight to it
        $hits = array_sum(array_map('count', $res));
        if ($hits === 1) {
            foreach (['customers' => 'customers/', 'vehicles' => 'vehicles/', 'jobs' => 'job-cards/', 'invoices' => 'invoices/', 'quotes' => 'quotes/'] as $k => $url) {
                if ($res[$k]) {
                    return redirect()->to(site_url($url . $res[$k][0]['id']));
                }
            }
        }
        return view('search/index', ['q' => $q, 'res' => $res, 'hits' => $hits]);
    }
}
