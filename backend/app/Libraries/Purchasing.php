<?php

namespace App\Libraries;

use Config\Database;

/**
 * Purchase orders → goods received (stock) → supplier bills (payables) → supplier payments.
 */
class Purchasing
{
    /** Bring stocked items on a bill into inventory at weighted-average cost (once per bill). */
    public static function receiveStock(int $billId): void
    {
        $db = Database::connect();
        $bill = $db->table('supplier_bills')->where('id', $billId)->get()->getRowArray();
        if (!$bill || (int) $bill['stock_received'] === 1) {
            return;
        }
        $lines = $db->table('line_items li')->select('li.product_id, li.quantity, li.unit_price, li.discount_percent, p.qty_on_hand, p.cost_price')
            ->join('products p', 'p.id = li.product_id')
            ->where(['li.document_type' => 'bill', 'li.document_id' => $billId, 'p.track_stock' => 1])->where('li.account_id', null)->get()->getResultArray();
        foreach ($lines as $l) {
            $qty = (float) $l['quantity'];
            $unitCost = (float) $l['unit_price'] * (1 - (float) $l['discount_percent'] / 100);
            $onHand = max(0, (float) $l['qty_on_hand']);
            $newQty = $onHand + $qty;
            $avg = $newQty > 0 ? ($onHand * (float) $l['cost_price'] + $qty * $unitCost) / $newQty : $unitCost;
            $db->table('products')->set('qty_on_hand', 'qty_on_hand + ' . $qty, false)->set('cost_price', round($avg, 2))->where('id', $l['product_id'])->update();
            $db->table('stock_movements')->insert([
                'product_id' => $l['product_id'], 'type' => 'in', 'quantity' => $qty, 'unit_cost' => round($unitCost, 2),
                'reference_type' => 'bill', 'reference_id' => $billId, 'note' => 'Received on ' . $bill['number'], 'user_id' => session('user_id'), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->table('supplier_bills')->where('id', $billId)->update(['stock_received' => 1]);
    }

    /** Undo stock received on a bill (when voiding / editing). */
    public static function reverseStock(int $billId): void
    {
        $db = Database::connect();
        foreach ($db->table('stock_movements')->where(['reference_type' => 'bill', 'reference_id' => $billId])->get()->getResultArray() as $m) {
            $db->table('products')->set('qty_on_hand', 'qty_on_hand - ' . (float) $m['quantity'], false)->where('id', $m['product_id'])->update();
        }
        $db->table('stock_movements')->where(['reference_type' => 'bill', 'reference_id' => $billId])->delete();
        $db->table('supplier_bills')->where('id', $billId)->update(['stock_received' => 0]);
    }

    /** Convert a purchase order into a supplier bill (goods received). */
    public static function billFromPo(int $poId, ?string $supplierInvoice = null, ?string $date = null): int
    {
        $db = Database::connect();
        $po = $db->table('purchase_orders')->where('id', $poId)->get()->getRowArray();
        $sup = $db->table('suppliers')->where('id', $po['supplier_id'])->get()->getRowArray();
        $date = $date ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('supplier_bills')->insert([
            'number' => Numbering::next('bill'), 'supplier_invoice' => $supplierInvoice, 'branch_id' => $po['branch_id'], 'supplier_id' => $po['supplier_id'],
            'purchase_order_id' => $poId, 'status' => 'unpaid', 'bill_date' => $date,
            'due_date' => date('Y-m-d', strtotime($date . ' +' . (int) ($sup['payment_terms_days'] ?? 30) . ' days')),
            'notes' => $po['notes'], 'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $billId = (int) $db->insertID();
        LineItems::copy('purchase_order', $poId, 'bill', $billId);
        self::receiveStock($billId);
        Ledger::postBill($billId);
        $db->table('purchase_orders')->where('id', $poId)->update(['status' => 'billed', 'received_at' => $now, 'updated_at' => $now]);
        $db->transComplete();
        Activity::log('purchase_order', $poId, 'received', 'Goods received, bill ' . $billId . ' created');
        return $billId;
    }

    public static function refreshBill(int $billId): void
    {
        $db = Database::connect();
        $b = $db->table('supplier_bills')->where('id', $billId)->get()->getRowArray();
        if (!$b || $b['status'] === 'void') {
            return;
        }
        $paid = (float) $db->table('supplier_payments')->selectSum('amount')->where(['bill_id' => $billId, 'status' => 'completed'])->get()->getRow()->amount;
        $bal = round((float) $b['total'] - $paid, 2);
        $db->table('supplier_bills')->where('id', $billId)->update([
            'amount_paid' => round($paid, 2), 'balance_due' => max(0, $bal),
            'status' => $bal <= 0.009 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function pay(int $billId, float $amount, int $paidFromId, string $method = 'eft', ?string $reference = null, ?string $paidAt = null): int
    {
        $db = Database::connect();
        $b = $db->table('supplier_bills')->where('id', $billId)->get()->getRowArray();
        $db->transStart();
        $db->table('supplier_payments')->insert([
            'number' => Numbering::next('supplier_payment'), 'supplier_id' => $b['supplier_id'], 'bill_id' => $billId, 'amount' => round($amount, 2),
            'method' => $method, 'paid_from_id' => $paidFromId, 'paid_at' => $paidAt ?: date('Y-m-d H:i:s'), 'reference' => $reference,
            'status' => 'completed', 'created_by' => session('user_id'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $pid = (int) $db->insertID();
        Ledger::postSupplierPayment($pid);
        self::refreshBill($billId);
        $db->transComplete();
        Activity::log('bill', $billId, 'payment', 'Paid supplier ' . money($amount));
        return $pid;
    }

    /** Aged creditors as at today. */
    public static function agedCreditors(): array
    {
        $rows = Database::connect()->table('supplier_bills b')->select('b.supplier_id, b.due_date, b.balance_due, s.name, s.phone')
            ->join('suppliers s', 's.id = b.supplier_id')->where('b.deleted_at', null)->whereIn('b.status', ['unpaid', 'partial'])->where('b.balance_due >', 0)->get()->getResultArray();
        $out = [];
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $r) {
            $id = $r['supplier_id'];
            $out[$id] ??= ['supplier_id' => $id, 'name' => $r['name'], 'phone' => $r['phone'], 'current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90plus' => 0, 'total' => 0];
            $days = (int) floor(($today - strtotime($r['due_date'])) / 86400);
            $bucket = $days <= 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'd90plus')));
            $out[$id][$bucket] += (float) $r['balance_due'];
            $out[$id]['total'] += (float) $r['balance_due'];
        }
        usort($out, static fn ($a, $b) => $b['total'] <=> $a['total']);
        return $out;
    }
}
