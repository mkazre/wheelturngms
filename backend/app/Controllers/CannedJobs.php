<?php

namespace App\Controllers;

use App\Libraries\LineItems;

/** Service packages / canned jobs: predefined labour + parts bundles with standard times. */
class CannedJobs extends BaseController
{
    public function index()
    {
        $rows = $this->db->table('canned_jobs c')
            ->select("c.*, (SELECT COALESCE(SUM(line_subtotal - line_discount),0) FROM line_items WHERE document_type = 'canned_job' AND document_id = c.id) price, (SELECT COUNT(*) FROM line_items WHERE document_type = 'canned_job' AND document_id = c.id) lines", false)
            ->orderBy('c.sort_order')->orderBy('c.name')->get()->getResultArray();
        return view('setup/canned_index', ['rows' => $rows]);
    }

    public function form(?int $id = null)
    {
        $c = $id ? $this->db->table('canned_jobs')->where('id', $id)->get()->getRowArray() : ['is_active' => 1, 'labour_hours' => 1];
        if ($id && !$c) {
            return $this->notFound('Package');
        }
        return view('setup/canned_form', ['c' => $c, 'lines' => $id ? LineItems::get('canned_job', $id) : []]);
    }

    public function save(?int $id = null)
    {
        $data = $this->only(['name', 'job_type', 'description']) + ['labour_hours' => (float) $this->input('labour_hours', 0), 'sort_order' => (int) $this->input('sort_order', 0), 'is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now()];
        if (!$data['name']) {
            return redirect()->back()->withInput()->with('error', 'Name is required.');
        }
        if ($id) {
            $this->db->table('canned_jobs')->where('id', $id)->update($data);
        } else {
            $this->db->table('canned_jobs')->insert($data + ['created_at' => $this->now()]);
            $id = (int) $this->db->insertID();
        }
        LineItems::sync('canned_job', $id, (array) $this->request->getPost('items'));
        return redirect()->to(site_url('canned-jobs'))->with('success', 'Service package saved.');
    }

    public function delete(int $id)
    {
        $this->db->table('canned_jobs')->where('id', $id)->delete();
        $this->db->table('line_items')->where(['document_type' => 'canned_job', 'document_id' => $id])->delete();
        return redirect()->to(site_url('canned-jobs'))->with('success', 'Package deleted.');
    }

    /** JSON lines for the line-item editor. Prices refresh from the current price list. */
    public function lines(int $id)
    {
        $rows = $this->db->table('line_items li')->select('li.item_type, li.product_id, li.description, li.quantity, li.unit_price, li.unit_cost, li.discount_percent, li.vat_rate, li.warranty_months, p.sell_price, p.cost_price')
            ->join('products p', 'p.id = li.product_id', 'left')->where(['li.document_type' => 'canned_job', 'li.document_id' => $id])->orderBy('li.sort_order')->get()->getResultArray();
        foreach ($rows as &$r) {
            if ($r['sell_price'] !== null) {
                $r['unit_price'] = $r['item_type'] === 'labour' ? (float) setting('default_labour_rate', $r['unit_price']) : (float) $r['sell_price'];
                $r['unit_cost'] = (float) $r['cost_price'];
            }
        }
        return $this->json($rows);
    }
}
