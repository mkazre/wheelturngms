<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Engagement;
use App\Libraries\Mailer;
use App\Libraries\Notify;

/**
 * Customer engagement: deferred-work follow-ups, feedback (NPS), email/SMS campaigns, batch statements.
 */
class Engage extends BaseController
{
    // ------------------------------------------------------------ deferred work

    /** Recommended work the customer hasn't done yet: revenue waiting to be won back. */
    public function followUps()
    {
        $rows = $this->db->table('inspections i')
            ->select('i.*, j.id job_id, j.number, j.collected_at, j.created_at job_date, v.id vehicle_id, v.registration, v.make, v.model, c.id customer_id, c.first_name, c.last_name, c.company_name, c.type, c.phone, c.email')
            ->join('job_cards j', 'j.id = i.job_card_id')->join('vehicles v', 'v.id = j.vehicle_id')->join('customers c', 'c.id = j.customer_id')
            ->whereIn('i.status', ['advisory', 'urgent'])->where('i.followed_up_at', null)
            ->groupStart()->where('i.customer_decision', null)->orWhere('i.customer_decision', 'declined')->groupEnd()
            ->whereIn('j.status', ['invoiced', 'collected', 'completed'])->where('j.deleted_at', null)
            ->orderBy("CASE i.status WHEN 'urgent' THEN 0 ELSE 1 END", '', false)->orderBy('j.id', 'DESC')->limit(300)->get()->getResultArray();
        $value = array_sum(array_column($rows, 'estimate'));
        return view('engage/followups', ['rows' => $rows, 'value' => $value]);
    }

    public function followUpDone(int $id)
    {
        $this->db->table('inspections')->where('id', $id)->update(['followed_up_at' => $this->now()]);
        return redirect()->back()->with('success', 'Marked as followed up.');
    }

    // ------------------------------------------------------------ feedback

    public function feedbackForm(string $token)
    {
        $f = $this->db->table('feedback')->where('token', $token)->get()->getRowArray() ?? $this->notFound('Survey');
        $score = $this->request->getGet('score');
        if ($score !== null && $f['submitted_at'] === null && ctype_digit((string) $score) && (int) $score <= 10) {
            $this->db->table('feedback')->where('id', $f['id'])->update(['score' => (int) $score, 'updated_at' => $this->now()]);
            $f['score'] = (int) $score;
        }
        return view('public/feedback', ['f' => $f]);
    }

    public function feedbackSubmit(string $token)
    {
        $f = $this->db->table('feedback')->where('token', $token)->get()->getRowArray() ?? $this->notFound('Survey');
        $score = (int) $this->request->getPost('score');
        $this->db->table('feedback')->where('id', $f['id'])->update(['score' => max(0, min(10, $score)), 'comment' => mb_substr(trim((string) $this->request->getPost('comment')), 0, 2000), 'submitted_at' => $this->now(), 'updated_at' => $this->now()]);
        if ($score <= 6) {
            Notify::staff('Unhappy customer: score ' . $score . '/10', mb_substr((string) $this->request->getPost('comment'), 0, 200), 'feedback', 'alert', null, 'manager');
        }
        return redirect()->to(site_url('feedback/' . $token . '?thanks=1'));
    }

    public function feedback()
    {
        [$from, $to] = $this->range('year');
        $rows = $this->db->table('feedback f')->select('f.*, c.first_name, c.last_name, c.company_name, c.type, j.number, j.id job_id')
            ->join('customers c', 'c.id = f.customer_id')->join('job_cards j', 'j.id = f.job_card_id', 'left')
            ->where('f.score IS NOT NULL')->where('f.updated_at >=', $from)->where('f.updated_at <=', $to . ' 23:59:59')->orderBy('f.updated_at', 'DESC')->get()->getResultArray();
        $n = count($rows);
        $promoters = count(array_filter($rows, static fn ($r) => $r['score'] >= 9));
        $detractors = count(array_filter($rows, static fn ($r) => $r['score'] <= 6));
        $sent = $this->db->table('feedback')->where('sent_at >=', $from)->countAllResults();
        return view('engage/feedback', [
            'rows' => $rows, 'from' => $from, 'to' => $to, 'nps' => $n ? round(($promoters - $detractors) / $n * 100) : null,
            'avg' => $n ? round(array_sum(array_column($rows, 'score')) / $n, 1) : null, 'promoters' => $promoters, 'detractors' => $detractors, 'passives' => $n - $promoters - $detractors,
            'responseRate' => $sent ? round($n / $sent * 100) : 0,
        ]);
    }

    // ------------------------------------------------------------ campaigns

    public const SEGMENTS = [
        'all'          => 'All customers who opted in to marketing',
        'service_due'  => 'Vehicles due for a service in the next 30 days',
        'inactive'     => 'No visit in the last 6 months',
        'new'          => 'New customers (last 60 days)',
        'business'     => 'Business & fleet accounts',
        'debtors'      => 'Customers with an outstanding balance',
    ];

    private function segment(string $seg): array
    {
        $b = $this->db->table('customers c')->select('c.id, c.first_name, c.last_name, c.company_name, c.type, c.email, c.phone, c.sms_consent')->where('c.deleted_at', null);
        match ($seg) {
            'service_due' => $b->where('c.id IN (SELECT customer_id FROM vehicles WHERE deleted_at IS NULL AND next_service_date BETWEEN ' . $this->db->escape(date('Y-m-d')) . ' AND ' . $this->db->escape(date('Y-m-d', strtotime('+30 days'))) . ')', null, false)->where('c.marketing_consent', 1),
            'inactive'    => $b->where('c.id NOT IN (SELECT customer_id FROM job_cards WHERE created_at >= ' . $this->db->escape(date('Y-m-d', strtotime('-6 months'))) . ')', null, false)->where('c.marketing_consent', 1),
            'new'         => $b->where('c.created_at >=', date('Y-m-d', strtotime('-60 days'))),
            'business'    => $b->where('c.type', 'business'),
            'debtors'     => $b->where("c.id IN (SELECT customer_id FROM invoices WHERE deleted_at IS NULL AND status IN ('unpaid','partial') AND balance_due > 0)", null, false),
            default       => $b->where('c.marketing_consent', 1),
        };
        return $b->get()->getResultArray();
    }

    public function campaigns()
    {
        $counts = [];
        foreach (array_keys(self::SEGMENTS) as $s) {
            $counts[$s] = count($this->segment($s));
        }
        return view('engage/campaigns', ['rows' => $this->db->table('campaigns')->orderBy('id', 'DESC')->get()->getResultArray(), 'segments' => self::SEGMENTS, 'counts' => $counts, 'smsOn' => (bool) setting('sms_provider')]);
    }

    public function sendCampaign()
    {
        $seg = (string) $this->request->getPost('segment');
        $channel = $this->request->getPost('channel') === 'sms' ? 'sms' : 'email';
        $subject = trim((string) $this->request->getPost('subject'));
        $body = trim((string) $this->request->getPost('body'));
        if (!isset(self::SEGMENTS[$seg]) || $body === '' || ($channel === 'email' && $subject === '')) {
            return redirect()->back()->withInput()->with('error', 'Choose an audience and write your message.');
        }
        $people = $this->segment($seg);
        $this->db->table('campaigns')->insert(['name' => mb_substr((string) ($this->input('name') ?: $subject ?: 'SMS campaign'), 0, 150), 'segment' => $seg, 'channel' => $channel, 'subject' => $subject, 'body' => $body, 'recipients' => count($people), 'status' => 'sending', 'created_by' => session('user_id'), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        $cid = (int) $this->db->insertID();
        $sent = 0;
        foreach ($people as $p) {
            $text = str_replace(['{first_name}', '{name}', '{company}', '{book_link}'], [$p['first_name'] ?: customer_name($p), customer_name($p), setting('company_name'), site_url('book')], $body);
            if ($channel === 'sms') {
                if ((int) $p['sms_consent'] === 1 && Notify::sms($p['phone'], $text . ' Reply STOP to opt out.', 'campaign', $cid)) {
                    $sent++;
                }
            } elseif ($p['email']) {
                $html = nl2br(esc($text)) . view('emails/_pay_button', ['url' => site_url('book'), 'label' => 'Book now']) . '<p style="font-size:11px;color:#94a3b8">To stop receiving promotions, reply "unsubscribe".</p>';
                if (Mailer::send($p['email'], str_replace('{first_name}', $p['first_name'], $subject), $html, [], 'campaign', $cid)) {
                    $sent++;
                }
            }
        }
        $this->db->table('campaigns')->where('id', $cid)->update(['sent_count' => $sent, 'status' => 'sent', 'sent_at' => $this->now()]);
        Activity::log('campaign', $cid, 'sent', "Campaign sent to {$sent} of " . count($people));
        return redirect()->back()->with($sent ? 'success' : 'warning', "Campaign sent to {$sent} of " . count($people) . ' customers.');
    }

    public function sendStatements()
    {
        $n = Engagement::sendStatements();
        return redirect()->back()->with($n ? 'success' : 'warning', $n ? "Statements emailed to {$n} customers." : 'No statements sent. Check SMTP settings and customer emails.');
    }
}
