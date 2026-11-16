<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\LineItems;

class Products extends BaseController
{
    private function query()
    {
        $b = $this->db->table('products p')->select('p.*, s.name supplier_name')->join('suppliers s', 's.id = p.supplier_id', 'left')->where('p.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('p.name', $q)->orLike('p.sku', $q)->orLike('p.barcode', $q)->orLike('p.brand', $q)->orLike('p.category', $q)->groupEnd();
        }
        if ($t = $this->request->getGet('type')) {
            $b->where('p.type', $t);
        }
        if ($this->request->getGet('filter') === 'low') {
            $b->where('p.track_stock', 1)->where('p.qty_on_hand <= p.reorder_level', null, false);
        }
        return $b->orderBy('p.name');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query(), 30);
        $val = $this->db->table('products')->select('COALESCE(SUM(qty_on_hand * cost_price),0) cost, COALESCE(SUM(qty_on_hand * sell_price),0) retail, COUNT(*) n')
            ->where(['deleted_at' => null, 'track_stock' => 1])->get()->getRowArray();
        $low = $this->db->table('products')->where(['deleted_at' => null, 'track_stock' => 1])->where('qty_on_hand <= reorder_level', null, false)->countAllResults();
        return view('products/index', compact('rows', 'pager', 'val', 'low'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['stock_value' => (float) $r['qty_on_hand'] * (float) $r['cost_price']], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Inventory', [
            'sku' => ['label' => 'SKU'], 'name' => ['label' => 'Item'], 'type' => ['label' => 'Type'], 'category' => ['label' => 'Category'], 'supplier_name' => ['label' => 'Supplier'],
            'cost_price' => ['label' => 'Cost', 'type' => 'money'], 'sell_price' => ['label' => 'Sell', 'type' => 'money'], 'qty_on_hand' => ['label' => 'On hand', 'type' => 'number'],
            'reorder_level' => ['label' => 'Reorder at', 'type' => 'number'], 'stock_value' => ['label' => 'Stock value', 'type' => 'money'],
        ], $rows, [], ['sku' => 'Total', 'stock_value' => array_sum(array_column($rows, 'stock_value'))]);
    }

    /** JSON search used by the line-item editor. */
    public function lookup()
    {
        $q = trim((string) $this->request->getGet('q'));
        $rows = $this->db->table('products')->select('id, sku, name, type, cost_price, sell_price, vat_rate, qty_on_hand, track_stock, warranty_months')
            ->where(['deleted_at' => null, 'is_active' => 1])
            ->groupStart()->like('name', $q)->orLike('sku', $q)->orLike('barcode', $q)->groupEnd()
            ->orderBy('name')->limit(12)->get()->getResultArray();
        return $this->json($rows);
    }

    public function form(?int $id = null)
    {
        $p = $id ? $this->db->table('products')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : ['type' => 'part', 'vat_rate' => setting('vat_rate', 15), 'track_stock' => 1, 'unit' => 'each', 'is_active' => 1];
        if ($id && !$p) {
            return $this->notFound('Item');
        }
        $moves = $id ? $this->db->table('stock_movements m')->select('m.*, u.first_name')->join('users u', 'u.id = m.user_id', 'left')->where('m.product_id', $id)->orderBy('m.id', 'DESC')->limit(30)->get()->getResultArray() : [];
        $suppliers = $this->db->table('suppliers')->select('id, name')->where('deleted_at', null)->orderBy('name')->get()->getResultArray();
        $categories = array_column($this->db->table('products')->distinct()->select('category')->where('category IS NOT NULL')->get()->getResultArray(), 'category');
        return view('products/form', compact('p', 'moves', 'suppliers', 'categories'));
    }

    public function save(?int $id = null)
    {
        if (!$this->validate(['name' => 'required|max_length[191]', 'type' => 'required|in_list[' . implode(',', array_keys(LineItems::TYPES)) . ']', 'sell_price' => 'required|decimal', 'cost_price' => 'permit_empty|decimal'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['sku', 'barcode', 'name', 'description', 'type', 'category', 'brand', 'unit', 'bin_location', 'supplier_id', 'warranty_months', 'labour_hours']) + [
            'cost_price' => (float) $this->input('cost_price', 0), 'sell_price' => (float) $this->input('sell_price', 0),
            'vat_rate' => (float) $this->input('vat_rate', setting('vat_rate', 15)), 'reorder_level' => (float) $this->input('reorder_level', 0),
            'track_stock' => $this->request->getPost('track_stock') ? 1 : 0, 'is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now(),
        ];
        if ($data['sku']) {
            $dupe = $this->db->table('products')->where(['sku' => $data['sku'], 'deleted_at' => null]);
            if ($id) {
                $dupe->where('id !=', $id);
            }
            if ($dupe->countAllResults()) {
                return redirect()->back()->withInput()->with('error', 'SKU ' . $data['sku'] . ' is already in use.');
            }
        }
        if ($id) {
            $this->db->table('products')->where('id', $id)->update($data);
        } else {
            $opening = (float) $this->input('opening_qty', 0);
            $this->db->table('products')->insert($data + ['qty_on_hand' => $opening, 'created_at' => $this->now()]);
            $id = (int) $this->db->insertID();
            if ($opening) {
                $this->db->table('stock_movements')->insert(['product_id' => $id, 'type' => 'in', 'quantity' => $opening, 'unit_cost' => $data['cost_price'], 'note' => 'Opening stock', 'user_id' => session('user_id'), 'created_at' => $this->now()]);
            }
        }
        return redirect()->to(site_url('inventory'))->with('success', 'Item saved.');
    }

    /** Stock receive / adjust / write-off. */
    public function adjust(int $id)
    {
        $qty = (float) $this->request->getPost('quantity');
        $type = $this->request->getPost('type') === 'out' ? 'out' : ($this->request->getPost('type') === 'adjust' ? 'adjust' : 'in');
        if ($qty == 0.0) {
            return redirect()->back()->with('error', 'Enter a quantity.');
        }
        $signed = $type === 'out' ? -abs($qty) : ($type === 'in' ? abs($qty) : $qty);
        $p = $this->db->table('products')->where('id', $id)->get()->getRowArray();
        $cost = (float) $this->input('unit_cost', $p['cost_price']);
        $this->db->transStart();
        $this->db->table('products')->set('qty_on_hand', 'qty_on_hand + ' . $signed, false)->where('id', $id)->update();
        if ($type === 'in' && $cost > 0) {
            // weighted average cost
            $newQty = (float) $p['qty_on_hand'] + $signed;
            $avg = $newQty > 0 ? ((float) $p['qty_on_hand'] * (float) $p['cost_price'] + $signed * $cost) / $newQty : $cost;
            $this->db->table('products')->where('id', $id)->update(['cost_price' => round(max(0, $avg), 2)]);
        }
        $this->db->table('stock_movements')->insert(['product_id' => $id, 'type' => $type, 'quantity' => $signed, 'unit_cost' => $cost, 'note' => $this->input('note'), 'user_id' => session('user_id'), 'created_at' => $this->now()]);
        $this->db->transComplete();
        Activity::log('product', $id, 'stock', ucfirst($type) . ' ' . $signed . ' × ' . $p['name']);
        return redirect()->back()->with('success', 'Stock updated.');
    }

    public function delete(int $id)
    {
        $this->db->table('products')->where('id', $id)->update(['deleted_at' => $this->now(), 'is_active' => 0]);
        return redirect()->to(site_url('inventory'))->with('success', 'Item archived.');
    }
}
