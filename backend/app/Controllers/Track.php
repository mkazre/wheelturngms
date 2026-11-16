<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;
use App\Libraries\DocData;
use App\Libraries\LineItems;
use App\Libraries\Notify;

/**
 * Public, token-secured customer job tracker ("journey tracker") with online approval of inspection findings.
 */
class Track extends BaseController
{
    private function job(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            $this->notFound('Job');
        }
        $job = $this->db->table('job_cards')->where(['public_token' => $token, 'deleted_at' => null])->get()->getRowArray();
        if (!$job) {
            $this->notFound('Job');
        }
        return DocData::jobCard((int) $job['id']);
    }

    public function show(string $token)
    {
        $d = $this->job($token);
        $id = (int) $d['job']['id'];
        $timeline = $this->db->table('activity_logs')->select('action, description, created_at')
            ->where(['entity_type' => 'job_card', 'entity_id' => $id])->whereIn('action', ['created', 'status', 'invoiced', 'signed'])->orderBy('id')->get()->getResultArray();
        $photos = array_values(array_filter(Files::for('job_card', $id), static fn ($f) => in_array($f['category'], ['photo', 'document'], true) && (Files::isImage($f) || str_starts_with((string) $f['mime'], 'video/'))));
        $invoice = $this->db->table('invoices')->select('number, total, balance_due, status, public_token')->where(['job_card_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->get()->getRowArray();
        $findings = array_values(array_filter($d['inspections'], static fn ($i) => in_array($i['status'], ['advisory', 'urgent'], true)));
        return view('public/track', $d + ['timeline' => $timeline, 'photos' => $photos, 'invoice' => $invoice, 'findings' => $findings, 'token' => $token]);
    }

    public function jobCardPdf(string $token)
    {
        $d = $this->job($token);
        $d['showPrices'] = true;
        return \App\Libraries\Pdf::respond(\App\Libraries\Pdf::render('pdf/job_card', $d), $d['job']['number'] . '.pdf');
    }

    /** Stream a photo/video that belongs to this job (public, token-checked). */
    public function file(string $token, int $fileId)
    {
        $d = $this->job($token);
        $f = $this->db->table('attachments')->where(['id' => $fileId, 'entity_type' => 'job_card', 'entity_id' => $d['job']['id']])->get()->getRowArray();
        if (!$f || !is_file(Files::path($f))) {
            $this->notFound('File');
        }
        return $this->response->setHeader('Content-Type', $f['mime'])->setHeader('Cache-Control', 'private, max-age=3600')->setHeader('X-Content-Type-Options', 'nosniff')->setBody((string) file_get_contents(Files::path($f)));
    }

    /** Customer approves or declines an inspection recommendation. Approved items with an estimate are added to the job. */
    public function decide(string $token)
    {
        $d = $this->job($token);
        $jobId = (int) $d['job']['id'];
        if (in_array($d['job']['status'], ['invoiced', 'collected', 'cancelled'], true)) {
            return redirect()->back()->with('info', 'This job is closed. Please contact us to book the recommended work.');
        }
        $itemId = (int) $this->request->getPost('item_id');
        $decision = $this->request->getPost('decision') === 'approve' ? 'approved' : 'declined';
        $item = $this->db->table('inspections')->where(['id' => $itemId, 'job_card_id' => $jobId])->get()->getRowArray();
        if (!$item || $item['customer_decision']) {
            return redirect()->back();
        }
        $this->db->transStart();
        $this->db->table('inspections')->where('id', $itemId)->update(['customer_decision' => $decision, 'decided_at' => $this->now()]);
        if ($decision === 'approved' && (float) $item['estimate'] > 0) {
            $max = (int) $this->db->table('line_items')->selectMax('sort_order')->where(['document_type' => 'job_card', 'document_id' => $jobId])->get()->getRow()->sort_order;
            $this->db->table('line_items')->insert(LineItems::calc([
                'item_type' => 'sundry', 'description' => 'Approved: ' . $item['item'] . ($item['note'] ? ' (' . $item['note'] . ')' : ''),
                'quantity' => 1, 'unit_price' => (float) $item['estimate'], 'vat_rate' => setting('vat_rate', 15),
            ]) + ['document_type' => 'job_card', 'document_id' => $jobId, 'sort_order' => $max + 1, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            LineItems::recalc('job_card', $jobId);
        }
        $this->db->transComplete();
        Activity::log('job_card', $jobId, 'customer_' . $decision, 'Customer ' . $decision . ' online: ' . $item['item'] . ((float) $item['estimate'] ? ' (' . money($item['estimate']) . ')' : ''));
        Notify::staff('Customer ' . $decision . ' work on ' . $d['job']['number'], $item['item'], 'job-cards/' . $jobId . '?tab=inspection', $decision === 'approved' ? 'check' : 'x');
        return redirect()->to(site_url('track/' . $token . '#findings'))->with('success', $decision === 'approved' ? 'Thank you! The work has been approved and added to your job.' : 'Noted. We have recorded that you declined this item.');
    }
}
