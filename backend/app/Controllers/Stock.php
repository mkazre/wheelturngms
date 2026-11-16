<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Ledger;
use App\Libraries\LineItems;
use App\Libraries\Numbering;

/** Reordering, stock takes, pricing matrix and barcode lookups. */
class Stock extends BaseController
{
    // ------------------------------------------------------------ pricing matrix

    /** Parse "0-100=120" lines into [[min, max, markup%], ...]. */
    public static function matrix(): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', (string) setting('markup_matrix', '')) as $line) {
            if (preg_match('/^\s*([\d.]+)\s*-\s*([\d.]+|\*)\s*=\s*([\d.]+)\s*$/', $line, $m)) {
                $out[] = [(float) $m[1], $m[2] === '*' ? PHP_FLOAT_MAX : (float) $m[2], (float) $m[3]];
            }
        }
        return $out;
    }

    public static function matrixPrice(float $cost): ?float
    {
        foreach (self::matrix() as [$min, $max, $markup]) {
            if ($cost >= $min && $cost <= $max) {
                $p = $cost * (1 + $markup / 100);
                return $p >= 100 ? round($p / 5) * 5 : round($p, 2); // round to the nearest R5 above R100
            }
        }
        return null;
    }

    public function pricing()
    {
        $preview = [];
        foreach ([50, 150, 400, 900, 2500, 6000] as $c) {
            $preview[$c] = self::matrixPrice($c);
        }
        return view('stock/pricing', ['matrix' => setting('markup_matrix', ''), 'preview' => $preview]);
    }

    public function savePricing()
    {
        \App\Libraries\Settings::set('markup_matrix', trim((string) $this->request->getPost('markup_matrix')), 'finance');
        if ($this->request->getPost('apply')) {
            $n = 0;
            foreach ($this->db->table('products')->where(['deleted_at' => null, 'is_active' => 1])->whereNotIn('type', ['labour', 'service'])->where('cost_price >', 0)->get()->getResultArray() as $p) {
                if (($price = self::matrixPrice((float) $p['cost_price'])) !== null) {
                    $this->db->table('products')->where('id', $p['id'])->update(['sell_price' => $price, 'updated_at' => $this->now()]);
                    $n++;
                }
            }
            return redirect()->back()->with('success', "Pricing matrix saved and applied to {$n} parts.");
        }
        return redirect()->back()->with('success', 'Pricing matrix saved.');
    }

    // ------------------------------------------------------------ barcode

    public function barcode()
    {
        $code = trim((string) $this->request->getGet('code'));
        $p = $this->db->table('products')->select('id, sku, barcode, name, type, cost_price, sell_price, vat_rate, qty_on_hand, track_stock, warranty_months')
            ->where('deleted_at', null)->groupStart()->where('barcode', $code)->orWhere('sku', $code)->groupEnd()->get()->getRowArray();
        return $this->json($p ?: ['error' => 'No item with barcode ' . $code], $p ? 200 : 404);
    }

    // ------------------------------------------------------------ reorder

    public function reorder()
    {
        $rows = $this->db->table('products p')->select('p.*, s.name supplier_name')->join('suppliers s', 's.id = p.supplier_id', 'left')
            ->where(['p.deleted_at' => null, 'p.track_stock' => 1, 'p.is_active' => 1])->where('p.qty_on_hand <= p.reorder_level', null, false)
            ->orderBy('s.name')->orderBy('p.name')->get()->getResultArray();
        foreach ($rows as &$r) {
            // Suggest topping up to 2× the reorder level (min 1), allowing for parts already on open POs
            $onOrder = (float) $this->db->table('line_items li')->selectSum('li.quantity')->join('purchase_orders po', "po.id = li.document_id AND li.document_type = 'purchase_order'")
                ->where('li.product_id', $r['id'])->whereIn('po.status', ['draft', 'sent'])->get()->getRow()->quantity;
            $r['on_order'] = $onOrder;
            $r['suggested'] = max(0, ceil(max(1, (float) $r['reorder_level'] * 2) - (float) $r['qty_on_hand'] - $onOrder));
        }
        unset($r);
        return view('stock/reorder', ['rows' => $rows]);
    }

    /** Create one draft purchase order per supplier from the ticked reorder lines. */
    public function createOrders()
    {
        $qty = array_filter(array_map('floatval', (array) $this->request->getPost('qty')), static fn ($q) => $q > 0);
        if (!$qty) {
            return redirect()->back()->with('error', 'Select at least one item with a quantity.');
        }
        $products = $this->db->table('products')->whereIn('id', array_keys($qty))->get()->getResultArray();
        $bySupplier = [];
        foreach ($products as $p) {
            $bySupplier[(int) $p['supplier_id']][] = $p;
        }
        if (isset($bySupplier[0])) {
            return redirect()->back()->with('error', 'Some items have no supplier. Set a supplier on: ' . implode(', ', array_column($bySupplier[0], 'name')));
        }
        $created = [];
        foreach ($bySupplier as $sid => $items) {
            $this->db->table('purchase_orders')->insert(['number' => Numbering::next('purchase_order'), 'branch_id' => session('branch_id'), 'supplier_id' => $sid, 'status' => 'draft', 'order_date' => date('Y-m-d'), 'expected_date' => date('Y-m-d', strtotime('+2 weekdays')), 'notes' => 'Stock replenishment', 'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            $poId = (int) $this->db->insertID();
            LineItems::sync('purchase_order', $poId, array_map(static fn ($p) => ['item_type' => $p['type'], 'product_id' => $p['id'], 'description' => $p['name'] . ($p['sku'] ? ' (' . $p['sku'] . ')' : ''), 'quantity' => $qty[$p['id']], 'unit_price' => $p['cost_price'], 'vat_rate' => $p['vat_rate']], $items));
            $created[] = $poId;
        }
        return redirect()->to(site_url(count($created) === 1 ? 'purchase-orders/' . $created[0] : 'purchase-orders?status=draft'))->with('success', count($created) . ' draft purchase order(s) created. Review and email them to suppliers.');
    }

    // ------------------------------------------------------------ stock takes

    public function takes()
    {
        return view('stock/takes', [
            'rows' => $this->db->table('stock_takes s')->select('s.*, u.first_name, (SELECT COUNT(*) FROM stock_take_lines WHERE stock_take_id = s.id) lines, (SELECT COUNT(*) FROM stock_take_lines WHERE stock_take_id = s.id AND counted_qty IS NOT NULL) counted')
                ->join('users u', 'u.id = s.created_by', 'left')->orderBy('s.id', 'DESC')->get()->getResultArray(),
            'categories' => array_filter(array_column($this->db->table('products')->distinct()->select('category')->where(['track_stock' => 1, 'deleted_at' => null])->get()->getResultArray(), 'category')),
        ]);
    }

    public function newTake()
    {
        $cat = $this->input('category');
        $b = $this->db->table('products')->where(['track_stock' => 1, 'deleted_at' => null, 'is_active' => 1]);
        if ($cat) {
            $b->where('category', $cat);
        }
        $products = $b->orderBy('bin_location')->orderBy('name')->get()->getResultArray();
        if (!$products) {
            return redirect()->back()->with('error', 'No stocked items to count.');
        }
        $this->db->table('stock_takes')->insert(['number' => 'ST-' . date('Ymd-His'), 'status' => 'counting', 'category' => $cat, 'notes' => $this->input('notes'), 'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        $id = (int) $this->db->insertID();
        foreach ($products as $p) {
            $this->db->table('stock_take_lines')->insert(['stock_take_id' => $id, 'product_id' => $p['id'], 'system_qty' => $p['qty_on_hand'], 'unit_cost' => $p['cost_price'], 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        }
        return redirect()->to(site_url('stock-takes/' . $id))->with('success', 'Stock take started with ' . count($products) . ' items. Count and enter quantities.');
    }

    public function take(int $id)
    {
        $t = $this->db->table('stock_takes')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Stock take');
        $lines = $this->db->table('stock_take_lines l')->select('l.*, p.name, p.sku, p.barcode, p.bin_location, p.unit')->join('products p', 'p.id = l.product_id')
            ->where('l.stock_take_id', $id)->orderBy('p.bin_location')->orderBy('p.name')->get()->getResultArray();
        return view('stock/take', ['t' => $t, 'lines' => $lines]);
    }

    public function saveCounts(int $id)
    {
        $t = $this->db->table('stock_takes')->where('id', $id)->get()->getRowArray();
        if (!$t || $t['status'] !== 'counting') {
            return redirect()->back()->with('error', 'This stock take is closed.');
        }
        foreach ((array) $this->request->getPost('count') as $lineId => $v) {
            $this->db->table('stock_take_lines')->where(['id' => (int) $lineId, 'stock_take_id' => $id])->update(['counted_qty' => $v === '' ? null : (float) $v, 'updated_at' => $this->now()]);
        }
        if (!$this->request->getPost('post')) {
            return redirect()->back()->with('success', 'Counts saved.');
        }
        // Post variances: adjust stock and book the value difference against cost of sales
        $value = 0.0;
        $this->db->transStart();
        foreach ($this->db->table('stock_take_lines')->where('stock_take_id', $id)->where('counted_qty IS NOT NULL')->get()->getResultArray() as $l) {
            $current = (float) $this->db->table('products')->select('qty_on_hand')->where('id', $l['product_id'])->get()->getRow()->qty_on_hand;
            $diff = round((float) $l['counted_qty'] - $current, 3);
            if ($diff == 0.0) {
                continue;
            }
            $this->db->table('products')->where('id', $l['product_id'])->update(['qty_on_hand' => (float) $l['counted_qty']]);
            $this->db->table('stock_movements')->insert(['product_id' => $l['product_id'], 'type' => 'adjust', 'quantity' => $diff, 'unit_cost' => $l['unit_cost'], 'reference_type' => 'stock_take', 'reference_id' => $id, 'note' => 'Stock take ' . $t['number'], 'user_id' => session('user_id'), 'created_at' => $this->now()]);
            $value += $diff * (float) $l['unit_cost'];
        }
        $value = round($value, 2);
        if ($value != 0.0) {
            Ledger::post('stock_take', $id, date('Y-m-d'), 'Stock take variance ' . $t['number'], $value > 0
                ? [['account' => '1200', 'debit' => $value], ['account' => '5000', 'credit' => $value]]
                : [['account' => '5000', 'debit' => -$value], ['account' => '1200', 'credit' => -$value]]);
        }
        $this->db->table('stock_takes')->where('id', $id)->update(['status' => 'posted', 'posted_at' => $this->now(), 'variance_value' => $value]);
        $this->db->transComplete();
        Activity::log('stock_take', $id, 'posted', 'Stock take posted, variance ' . money($value));
        return redirect()->back()->with('success', 'Stock take posted. Variance ' . money($value) . ' booked to cost of sales.');
    }
}
