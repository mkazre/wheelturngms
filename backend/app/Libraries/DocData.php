<?php

namespace App\Libraries;

use Config\Database;

/**
 * Loads a document with everything needed to display / print / email it.
 */
class DocData
{
    private static function base(string $table, int $id, string $key): ?array
    {
        $db  = Database::connect();
        $doc = $db->table($table)->where('id', $id)->get()->getRowArray();
        if (!$doc) {
            return null;
        }
        return [
            $key       => $doc,
            'customer' => $db->table('customers')->where('id', $doc['customer_id'])->get()->getRowArray(),
            'vehicle'  => !empty($doc['vehicle_id']) ? $db->table('vehicles')->where('id', $doc['vehicle_id'])->get()->getRowArray() : null,
            'branch'   => !empty($doc['branch_id']) ? $db->table('branches')->where('id', $doc['branch_id'])->get()->getRowArray() : null,
            'company'  => Settings::all(),
        ];
    }

    public static function invoice(int $id): ?array
    {
        $d = self::base('invoices', $id, 'invoice');
        if ($d) {
            $d['items']    = LineItems::get('invoice', $id);
            $d['payments'] = Database::connect()->table('payments')->where(['invoice_id' => $id, 'status' => 'completed'])->orderBy('paid_at')->get()->getResultArray();
            $d['job']      = $d['invoice']['job_card_id'] ? Database::connect()->table('job_cards')->select('id, number, odometer_in, odometer_out')->where('id', $d['invoice']['job_card_id'])->get()->getRowArray() : null;
        }
        return $d;
    }

    public static function quote(int $id): ?array
    {
        $d = self::base('quotes', $id, 'quote');
        if ($d) {
            $d['items'] = LineItems::get('quote', $id);
        }
        return $d;
    }

    public static function jobCard(int $id): ?array
    {
        $db = Database::connect();
        $d  = self::base('job_cards', $id, 'job');
        if ($d) {
            $d['items']       = LineItems::get('job_card', $id);
            $d['inspections'] = $db->table('inspections')->where('job_card_id', $id)->orderBy('sort_order')->get()->getResultArray();
            $d['mechanics']   = $db->table('job_card_mechanics jm')->select('u.id, u.first_name, u.last_name, u.avatar')
                ->join('users u', 'u.id = jm.user_id')->where('jm.job_card_id', $id)->get()->getResultArray();
            $d['advisor']     = $d['job']['advisor_id'] ? $db->table('users')->select('id, first_name, last_name')->where('id', $d['job']['advisor_id'])->get()->getRowArray() : null;
        }
        return $d;
    }
}
