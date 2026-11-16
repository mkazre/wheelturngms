<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\AiAssistant;
use App\Libraries\DocData;

/** AI workshop assistant: repair chat, fault-code explainer, customer summaries, upsell suggestions. */
class Assistant extends BaseController
{
    public function index()
    {
        $jobId = (int) $this->request->getGet('job');
        $job = $jobId ? DocData::jobCard($jobId) : null;
        $convs = $this->db->table('ai_conversations')->select('id, title, job_card_id, updated_at')->where('user_id', session('user_id'))->orderBy('id', 'DESC')->limit(15)->get()->getResultArray();
        $current = null;
        if ($cid = (int) $this->request->getGet('c')) {
            $current = $this->db->table('ai_conversations')->where(['id' => $cid, 'user_id' => session('user_id')])->get()->getRowArray();
            if ($current && !$job && $current['job_card_id']) {
                $job = DocData::jobCard((int) $current['job_card_id']);
            }
        }
        return view('assistant/index', ['enabled' => AiAssistant::enabled(), 'job' => $job, 'convs' => $convs, 'current' => $current,
            'messages' => $current ? (json_decode((string) $current['messages'], true) ?: []) : []]);
    }

    /** AJAX chat turn. Conversation history is stored server-side per user. */
    public function ask()
    {
        $q = trim((string) $this->request->getPost('message'));
        if ($q === '') {
            return $this->json(['error' => 'Type a question.'], 422);
        }
        $convId = (int) $this->request->getPost('conversation_id');
        $jobId = (int) $this->request->getPost('job_card_id') ?: null;
        $conv = $convId ? $this->db->table('ai_conversations')->where(['id' => $convId, 'user_id' => session('user_id')])->get()->getRowArray() : null;
        $history = $conv ? (json_decode((string) $conv['messages'], true) ?: []) : [];
        $history[] = ['role' => 'user', 'content' => mb_substr($q, 0, 4000)];

        $context = null;
        if ($jobId) {
            $d = DocData::jobCard($jobId);
            $context = $d ? "The technician is working on this job:\n" . AiAssistant::jobContext($d['job'], $d['vehicle'], $d['inspections']) : null;
        }
        try {
            $answer = AiAssistant::ask(array_slice($history, -20), 'medium', 4000, $context);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage(), 'csrf' => csrf_hash()], 503);
        }
        $history[] = ['role' => 'assistant', 'content' => $answer];
        if ($conv) {
            $this->db->table('ai_conversations')->where('id', $conv['id'])->update(['messages' => json_encode($history), 'updated_at' => $this->now()]);
        } else {
            $this->db->table('ai_conversations')->insert(['user_id' => session('user_id'), 'job_card_id' => $jobId, 'title' => mb_substr($q, 0, 120), 'messages' => json_encode($history), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            $convId = (int) $this->db->insertID();
        }
        return $this->json(['answer' => $answer, 'html' => self::render($answer), 'conversation_id' => $convId ?: $conv['id'], 'csrf' => csrf_hash()]);
    }

    /** Minimal, safe markdown → HTML for assistant replies (headings, bold, lists, code). */
    public static function render(string $md): string
    {
        $html = esc($md);
        $html = preg_replace('/^#{1,4}\s*(.+)$/m', '<h4 class="mt-3 font-bold">$1</h4>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code class="rounded bg-slate-100 px-1 dark:bg-white/10">$1</code>', $html);
        $html = preg_replace('/^\s*[-*]\s+(.+)$/m', '<li class="ml-5 list-disc">$1</li>', $html);
        $html = preg_replace('/^\s*(\d+)\.\s+(.+)$/m', '<li class="ml-5 list-decimal">$2</li>', $html);
        return nl2br(preg_replace("/\n?(<li[^>]*>.*<\/li>)\n?/", '$1', $html), false);
    }

    /** Draft the customer-friendly "work performed" text for a job card. */
    public function summary(int $jobId)
    {
        $d = DocData::jobCard($jobId) ?? $this->notFound('Job card');
        try {
            $text = AiAssistant::customerSummary(AiAssistant::jobContext($d['job'], $d['vehicle'], $d['inspections']), $d['items']);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage(), 'csrf' => csrf_hash()], 503);
        }
        if ($this->request->getPost('save')) {
            $this->db->table('job_cards')->where('id', $jobId)->update(['work_performed' => $text, 'updated_at' => $this->now()]);
            Activity::log('job_card', $jobId, 'ai_summary', 'Customer summary written with the AI assistant');
        }
        return $this->json(['text' => $text, 'csrf' => csrf_hash()]);
    }

    public function faultCodes()
    {
        $codes = strtoupper(trim((string) $this->request->getPost('codes')));
        if (!preg_match('/^[A-Z0-9 ,\-\/]{4,200}$/', $codes)) {
            return $this->json(['error' => 'Enter one or more fault codes, e.g. P0301, P0171.', 'csrf' => csrf_hash()], 422);
        }
        $vehicle = mb_substr(trim((string) $this->request->getPost('vehicle')), 0, 120) ?: 'light vehicle';
        try {
            $text = AiAssistant::explainFaultCodes($codes, $vehicle);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage(), 'csrf' => csrf_hash()], 503);
        }
        return $this->json(['html' => self::render($text), 'csrf' => csrf_hash()]);
    }

    public function upsell(int $vehicleId)
    {
        $v = $this->db->table('vehicles')->where('id', $vehicleId)->get()->getRowArray() ?? $this->notFound('Vehicle');
        $history = [];
        foreach ($this->db->table('job_cards j')->select('j.created_at, j.odometer_in, li.description')->join('line_items li', "li.document_type = 'job_card' AND li.document_id = j.id")
            ->where(['j.vehicle_id' => $vehicleId, 'j.deleted_at' => null])->orderBy('j.id', 'DESC')->limit(60)->get()->getResultArray() as $h) {
            $history[] = date('M Y', strtotime($h['created_at'])) . ($h['odometer_in'] ? ' @ ' . number_format((int) $h['odometer_in']) . ' km' : '') . ': ' . $h['description'];
        }
        $desc = trim(implode(' ', array_filter([$v['year'], $v['make'], $v['model'], $v['variant'], $v['engine_size'], $v['fuel_type']])));
        try {
            $text = AiAssistant::upsellSuggestions($desc ?: 'vehicle', (int) $v['odometer'], $history);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage(), 'csrf' => csrf_hash()], 503);
        }
        return $this->json(['html' => self::render($text), 'csrf' => csrf_hash()]);
    }
}
