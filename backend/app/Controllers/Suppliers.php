<?php

namespace App\Controllers;

class Suppliers extends BaseController
{
    public function index()
    {
        $b = $this->db->table('suppliers s')->select('s.*')
            ->select('(SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id AND p.deleted_at IS NULL) products')
            ->select('(SELECT COALESCE(SUM(e.total),0) FROM expenses e WHERE e.supplier_id = s.id AND e.deleted_at IS NULL) spend')
            ->where('s.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('s.name', $q)->orLike('s.contact_person', $q)->orLike('s.email', $q)->orLike('s.phone', $q)->groupEnd();
        }
        [$rows, $pager] = $this->paginate($b->orderBy('s.name'));
        return view('suppliers/index', compact('rows', 'pager'));
    }

    public function form(?int $id = null)
    {
        $s = $id ? $this->db->table('suppliers')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : ['payment_terms_days' => 30];
        if ($id && !$s) {
            return $this->notFound('Supplier');
        }
        return view('suppliers/form', ['s' => $s, 'files' => $id ? \App\Libraries\Attachments::for('supplier', $id) : []]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['name' => 'required|max_length[191]', 'email' => 'permit_empty|valid_email'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['name', 'contact_person', 'email', 'phone', 'vat_number', 'address', 'account_number', 'notes']) + ['payment_terms_days' => (int) $this->input('payment_terms_days', 30), 'updated_at' => $this->now()];
        if ($id) {
            $this->db->table('suppliers')->where('id', $id)->update($data);
        } else {
            $this->db->table('suppliers')->insert($data + ['created_at' => $this->now()]);
        }
        return redirect()->to(site_url('suppliers'))->with('success', 'Supplier saved.');
    }

    public function delete(int $id)
    {
        $this->db->table('suppliers')->where('id', $id)->update(['deleted_at' => $this->now()]);
        return redirect()->to(site_url('suppliers'))->with('success', 'Supplier archived.');
    }
}
