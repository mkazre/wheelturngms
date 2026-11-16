<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Billing;
use App\Libraries\Gateways;

/**
 * Server-to-server payment notifications. CSRF is disabled for webhooks/* in Config\Filters.
 */
class Webhooks extends BaseController
{
    /** PayFast ITN (Instant Transaction Notification). */
    public function payfast()
    {
        $post = $this->request->getPost();
        log_message('info', 'PayFast ITN received: ' . json_encode($post));
        $invoiceId = (int) ($post['custom_int1'] ?? 0);
        $inv = $invoiceId ? $this->db->table('invoices')->where('id', $invoiceId)->get()->getRowArray() : null;
        if (!$inv) {
            return $this->response->setStatusCode(200)->setBody('Unknown invoice');
        }
        [$valid, $reason] = Gateways::payfastValidateItn($post, (float) $inv['balance_due']);
        if (!$valid) {
            log_message('error', 'PayFast ITN rejected for invoice ' . $inv['number'] . ': ' . $reason);
            Activity::log('invoice', $invoiceId, 'payfast_rejected', 'PayFast notification rejected: ' . $reason);
            return $this->response->setStatusCode(200)->setBody('Rejected');
        }
        $txn = (string) ($post['pf_payment_id'] ?? '');
        if (($post['payment_status'] ?? '') === 'COMPLETE' && !$this->alreadyRecorded($txn)) {
            $pid = Billing::recordPayment($invoiceId, (float) $post['amount_gross'], 'payfast', [
                'reference' => $post['m_payment_id'] ?? null, 'gateway_txn_id' => $txn, 'gateway_payload' => json_encode($post),
            ]);
            $fee = abs((float) ($post['amount_fee'] ?? 0));
            if ($fee > 0) {
                // Book the gateway fee: Dr Payment Gateway Fees, Cr PayFast clearing
                \App\Libraries\Ledger::post('payfast_fee', $pid, date('Y-m-d'), 'PayFast fee on ' . $inv['number'], [
                    ['account' => '6130', 'debit' => $fee], ['account' => '1030', 'credit' => $fee],
                ]);
            }
        }
        return $this->response->setStatusCode(200)->setBody('OK');
    }

    /** Ozow notification (server-to-server). */
    public function ozow()
    {
        $post = $this->request->getPost();
        log_message('info', 'Ozow notify received: ' . json_encode($post));
        if (!Gateways::ozowValidateResponse($post)) {
            log_message('error', 'Ozow notify hash mismatch');
            return $this->response->setStatusCode(400)->setBody('Invalid hash');
        }
        $invoiceId = (int) ($post['Optional1'] ?? 0);
        $inv = $invoiceId ? $this->db->table('invoices')->where('id', $invoiceId)->get()->getRowArray() : null;
        if (!$inv) {
            return $this->response->setStatusCode(200)->setBody('Unknown invoice');
        }
        $status = strtolower((string) ($post['Status'] ?? ''));
        $txn = (string) ($post['TransactionId'] ?? '');
        $isTest = strtolower((string) ($post['IsTest'] ?? 'false')) === 'true';
        if ($isTest && (int) setting('ozow_test_mode', 1) !== 1) {
            return $this->response->setStatusCode(200)->setBody('Test transaction ignored');
        }
        if ($status === 'complete' && !$this->alreadyRecorded($txn)) {
            // Optional API double-check
            $confirm = Gateways::ozowConfirm((string) $post['TransactionReference']);
            if ($confirm !== null && strtolower((string) ($confirm['status'] ?? $confirm['Status'] ?? '')) !== 'complete') {
                log_message('error', 'Ozow API did not confirm transaction ' . $txn);
                return $this->response->setStatusCode(200)->setBody('Not confirmed');
            }
            $amount = min((float) $post['Amount'], (float) $inv['balance_due']);
            Billing::recordPayment($invoiceId, $amount, 'ozow', [
                'reference' => $post['TransactionReference'] ?? null, 'gateway_txn_id' => $txn, 'gateway_payload' => json_encode($post),
            ]);
        } elseif (in_array($status, ['cancelled', 'error', 'abandoned'], true)) {
            Activity::log('invoice', $invoiceId, 'ozow_' . $status, 'Ozow payment ' . $status . (!empty($post['StatusMessage']) ? ': ' . $post['StatusMessage'] : ''));
        }
        return $this->response->setStatusCode(200)->setBody('OK');
    }

    private function alreadyRecorded(string $txn): bool
    {
        return $txn !== '' && $this->db->table('payments')->where('gateway_txn_id', $txn)->countAllResults() > 0;
    }
}
