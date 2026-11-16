<?php

namespace App\Libraries;

use Config\Database;

/**
 * Job profitability: revenue vs actual labour cost (clocked time × technician cost rate) and parts cost.
 */
class JobCosting
{
    public static function for(int $jobId): array
    {
        $db = Database::connect();
        $job = $db->table('job_cards')->select('subtotal, discount_total')->where('id', $jobId)->get()->getRowArray();
        $labour = $db->table('time_logs t')->select('COALESCE(SUM(t.minutes / 60.0 * u.cost_rate),0) cost, COALESCE(SUM(t.minutes),0) mins', false)
            ->join('users u', 'u.id = t.user_id')->where('t.job_card_id', $jobId)->get()->getRowArray();
        $parts = $db->table('line_items')->select('COALESCE(SUM(unit_cost * quantity),0) cost', false)
            ->where(['document_type' => 'job_card', 'document_id' => $jobId])->whereNotIn('item_type', ['labour', 'service'])->get()->getRowArray();
        $soldHours = (float) $db->table('line_items')->selectSum('quantity')->where(['document_type' => 'job_card', 'document_id' => $jobId, 'item_type' => 'labour'])->get()->getRow()->quantity;
        $revenue = (float) $job['subtotal'] - (float) $job['discount_total'];
        $cost = (float) $labour['cost'] + (float) $parts['cost'];
        $profit = $revenue - $cost;
        $clocked = (float) $labour['mins'] / 60;
        return [
            'revenue' => round($revenue, 2), 'labour_cost' => round((float) $labour['cost'], 2), 'parts_cost' => round((float) $parts['cost'], 2),
            'profit' => round($profit, 2), 'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0,
            'hours_clocked' => round($clocked, 2), 'hours_sold' => round($soldHours, 2),
            'efficiency' => $clocked > 0 ? round($soldHours / $clocked * 100) : null,
        ];
    }

    /** Persist cost snapshot on the job (used by reports). */
    public static function snapshot(int $jobId): void
    {
        $c = self::for($jobId);
        Database::connect()->table('job_cards')->where('id', $jobId)->update(['labour_cost' => $c['labour_cost'], 'parts_cost' => $c['parts_cost']]);
    }
}
