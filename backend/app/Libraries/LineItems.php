<?php

namespace App\Libraries;

use Config\Database;

/**
 * Shared line-item engine for job cards, quotes and invoices.
 * Calculates per-line VAT (SA 15% by default) and rolls totals up to the header table.
 */
class LineItems
{
    public const TABLES = ['job_card' => 'job_cards', 'quote' => 'quotes', 'invoice' => 'invoices', 'credit_note' => 'credit_notes', 'purchase_order' => 'purchase_orders', 'bill' => 'supplier_bills', 'canned_job' => 'canned_jobs'];
    public const TYPES  = ['labour' => 'Labour', 'part' => 'Part', 'fluid' => 'Fluid / Oil', 'tyre' => 'Tyre', 'service' => 'Service', 'sublet' => 'Sublet', 'sundry' => 'Sundry'];

    public static function calc(array $line): array
    {
        $vatOn = (int) Settings::get('vat_registered', 1) === 1;
        $qty   = round((float) ($line['quantity'] ?? 1), 3);
        $price = round((float) ($line['unit_price'] ?? 0), 2);
        $disc  = min(100, max(0, (float) ($line['discount_percent'] ?? 0)));
        $rate  = $vatOn ? (float) ($line['vat_rate'] ?? Settings::get('vat_rate', 15)) : 0;

        $sub      = round($qty * $price, 2);
        $discount = round($sub * $disc / 100, 2);
        $net      = $sub - $discount;
        $vat      = round($net * $rate / 100, 2);

        return [
            'item_type'        => array_key_exists($line['item_type'] ?? '', self::TYPES) ? $line['item_type'] : 'part',
            'product_id'       => !empty($line['product_id']) ? (int) $line['product_id'] : null,
            'description'      => trim((string) ($line['description'] ?? '')),
            'quantity'         => $qty,
            'unit_cost'        => round((float) ($line['unit_cost'] ?? 0), 2),
            'unit_price'       => $price,
            'discount_percent' => $disc,
            'vat_rate'         => $rate,
            'line_subtotal'    => $sub,
            'line_discount'    => $discount,
            'line_vat'         => $vat,
            'line_total'       => round($net + $vat, 2),
            'mechanic_id'      => !empty($line['mechanic_id']) ? (int) $line['mechanic_id'] : null,
            'account_id'       => !empty($line['account_id']) ? (int) $line['account_id'] : null,
            'warranty_months'  => isset($line['warranty_months']) && $line['warranty_months'] !== '' ? (int) $line['warranty_months'] : null,
        ];
    }

    /** Replace all lines of a document and recalculate its totals. */
    public static function sync(string $docType, int $docId, array $lines): array
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('line_items')->where(['document_type' => $docType, 'document_id' => $docId])->delete();

        $i = 0;
        foreach ($lines as $line) {
            if (trim((string) ($line['description'] ?? '')) === '') {
                continue;
            }
            $row = self::calc($line) + [
                'document_type' => $docType, 'document_id' => $docId, 'sort_order' => $i++,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $db->table('line_items')->insert($row);
        }
        return self::recalc($docType, $docId);
    }

    public static function recalc(string $docType, int $docId): array
    {
        $db = Database::connect();
        $t  = $db->table('line_items')
            ->select('COALESCE(SUM(line_subtotal),0) s, COALESCE(SUM(line_discount),0) d, COALESCE(SUM(line_vat),0) v, COALESCE(SUM(line_total),0) t, COALESCE(SUM(unit_cost*quantity),0) c')
            ->where(['document_type' => $docType, 'document_id' => $docId])->get()->getRowArray();

        $totals = [
            'subtotal'       => round((float) $t['s'], 2),
            'discount_total' => round((float) $t['d'], 2),
            'vat_total'      => round((float) $t['v'], 2),
            'total'          => round((float) $t['t'], 2),
        ];
        if ($docType === 'canned_job') {
            return $totals; // service packages have no header totals
        }
        $update = $totals;
        if ($docType === 'invoice') {
            $inv = $db->table('invoices')->select('amount_paid, amount_credited')->where('id', $docId)->get()->getRowArray();
            $update['cost_total']  = round((float) $t['c'], 2);
            $update['balance_due'] = round($totals['total'] - (float) ($inv['amount_paid'] ?? 0) - (float) ($inv['amount_credited'] ?? 0), 2);
        }
        if ($docType === 'bill') {
            $bill = $db->table('supplier_bills')->select('amount_paid')->where('id', $docId)->get()->getRowArray();
            $update['balance_due'] = round($totals['total'] - (float) ($bill['amount_paid'] ?? 0), 2);
        }
        $db->table(self::TABLES[$docType])->where('id', $docId)->update($update);
        return $totals;
    }

    public static function get(string $docType, int $docId): array
    {
        return Database::connect()->table('line_items li')
            ->select("li.*, p.sku, CONCAT_WS(' ', u.first_name, u.last_name) mechanic_name, a.code account_code, a.name account_name", false)
            ->join('accounts a', 'a.id = li.account_id', 'left')
            ->join('products p', 'p.id = li.product_id', 'left')
            ->join('users u', 'u.id = li.mechanic_id', 'left')
            ->where(['li.document_type' => $docType, 'li.document_id' => $docId])
            ->orderBy('li.sort_order')->get()->getResultArray();
    }

    /** Copy lines from one document to another (quote → job card → invoice). */
    public static function copy(string $fromType, int $fromId, string $toType, int $toId): array
    {
        $lines = Database::connect()->table('line_items')
            ->where(['document_type' => $fromType, 'document_id' => $fromId])->orderBy('sort_order')->get()->getResultArray();
        return self::sync($toType, $toId, $lines);
    }
}
