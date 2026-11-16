<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Billing;
use App\Libraries\DocData;
use App\Libraries\Gateways;
use App\Libraries\Pdf;

/**
 * Public, token-secured pages for customers: view & pay invoices, approve quotes.
 */
class Pay extends BaseController
{
    private function invoiceByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            $this->notFound('Invoice');
        }
        $inv = $this->db->table('invoices')->where(['public_token' => $token, 'deleted_at' => null])->get()->getRowArray();
        if (!$inv || $inv['status'] === 'draft') {
            $this->notFound('Invoice');
        }
        return DocData::invoice((int) $inv['id']);
    }

    private function amountToPay(array $inv): float
    {
        $amt = (float) $inv['balance_due'];
        if ((int) setting('allow_partial_online_payment', 0) === 1 && ($req = (float) $this->request->getPost('amount')) > 0) {
            $amt = min($amt, $req);
        }
        return round($amt, 2);
    }

    public function show(string $token)
    {
        $d = $this->invoiceByToken($token);
        return view('public/invoice', $d + [
            'cancelled' => $this->request->getGet('cancelled'), 'error' => $this->request->getGet('error'),
            'payfast' => (int) setting('payfast_enabled') === 1 && setting('payfast_merchant_id'),
            'ozow' => (int) setting('ozow_enabled') === 1 && setting('ozow_site_code'),
        ]);
    }

    public function pdf(string $token)
    {
        $d = $this->invoiceByToken($token);
        return Pdf::respond(Pdf::render('pdf/invoice', $d), $d['invoice']['number'] . '.pdf');
    }

    public function success(string $token)
    {
        $d = $this->invoiceByToken($token);
        return view('public/success', $d);
    }

    /** Builds the signed PayFast form and auto-submits it. */
    public function payfast(string $token)
    {
        $d = $this->invoiceByToken($token);
        $amount = $this->amountToPay($d['invoice']);
        if ($amount <= 0 || (int) setting('payfast_enabled') !== 1) {
            return redirect()->to(site_url('pay/' . $token));
        }
        Activity::log('invoice', (int) $d['invoice']['id'], 'payfast_started', 'Customer started PayFast checkout for ' . money($amount));
        return view('public/redirect', ['action' => Gateways::payfastUrl(), 'fields' => Gateways::payfastFields($d['invoice'], $d['customer'], $amount), 'gateway' => 'PayFast']);
    }

    public function ozow(string $token)
    {
        $d = $this->invoiceByToken($token);
        $amount = $this->amountToPay($d['invoice']);
        if ($amount <= 0 || (int) setting('ozow_enabled') !== 1) {
            return redirect()->to(site_url('pay/' . $token));
        }
        Activity::log('invoice', (int) $d['invoice']['id'], 'ozow_started', 'Customer started Ozow checkout for ' . money($amount));
        return view('public/redirect', ['action' => Gateways::OZOW_URL, 'fields' => Gateways::ozowFields($d['invoice'], $d['customer'], $amount), 'gateway' => 'Ozow']);
    }

    // ------------------------------------------------------------ quotes

    private function quoteByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            $this->notFound('Quote');
        }
        $q = $this->db->table('quotes')->where(['public_token' => $token, 'deleted_at' => null])->get()->getRowArray();
        if (!$q) {
            $this->notFound('Quote');
        }
        return DocData::quote((int) $q['id']);
    }

    public function quote(string $token)
    {
        return view('public/quote', $this->quoteByToken($token));
    }

    public function quotePdf(string $token)
    {
        $d = $this->quoteByToken($token);
        return Pdf::respond(Pdf::render('pdf/quote', $d), $d['quote']['number'] . '.pdf');
    }

    public function quoteRespond(string $token)
    {
        $d = $this->quoteByToken($token);
        $q = $d['quote'];
        if (!in_array($q['status'], ['draft', 'sent'], true)) {
            return redirect()->to(site_url('q/' . $token))->with('info', 'This quote has already been ' . $q['status'] . '.');
        }
        $accept = $this->request->getPost('decision') === 'accept';
        $this->db->table('quotes')->where('id', $q['id'])->update([
            'status' => $accept ? 'accepted' : 'declined', ($accept ? 'accepted_at' : 'declined_at') => $this->now(), 'updated_at' => $this->now(),
        ]);
        $comment = trim((string) $this->request->getPost('comment'));
        \App\Libraries\Notify::staff('Quote ' . $q['number'] . ' ' . ($accept ? 'accepted' : 'declined') . ' online', customer_name($d['customer']) . ' · ' . money($q['total']), 'quotes/' . $q['id'], $accept ? 'check' : 'x');
        Activity::log('quote', (int) $q['id'], $accept ? 'accepted' : 'declined', 'Customer ' . ($accept ? 'accepted' : 'declined') . ' the quote online' . ($comment ? ': "' . mb_substr($comment, 0, 300) . '"' : ''));
        // Notify the workshop
        \App\Libraries\Mailer::send(setting('company_email'), 'Quote ' . $q['number'] . ' ' . ($accept ? 'ACCEPTED' : 'declined') . ' by ' . customer_name($d['customer']),
            '<p>Quote <b>' . esc($q['number']) . '</b> (' . money($q['total']) . ') was <b>' . ($accept ? 'accepted' : 'declined') . '</b> online by ' . esc(customer_name($d['customer'])) . '.</p>'
            . ($comment ? '<p>Comment: <i>' . esc($comment) . '</i></p>' : '') . view('emails/_pay_button', ['url' => site_url('quotes/' . $q['id']), 'label' => 'Open quote']), [], 'quote', (int) $q['id']);
        return redirect()->to(site_url('q/' . $token))->with('success', $accept ? 'Thank you! Your quote has been accepted and our team will be in touch to book your vehicle in.' : 'Thanks for letting us know. The quote has been declined.');
    }
}
