<?php

namespace App\Libraries;

use Config\Database;

/**
 * Business workflows that span several documents: conversions, payments, emails.
 */
class Billing
{
    public static function token(): string
    {
        return bin2hex(random_bytes(20));
    }

    public static function db()
    {
        return Database::connect();
    }

    // ------------------------------------------------------------ conversions

    public static function quoteToJobCard(int $quoteId): int
    {
        $db = self::db();
        $q  = $db->table('quotes')->where('id', $quoteId)->get()->getRowArray();
        if (!empty($q['job_card_id'])) {
            LineItems::copy('quote', $quoteId, 'job_card', (int) $q['job_card_id']);
            $db->table('quotes')->where('id', $quoteId)->update(['status' => 'converted']);
            return (int) $q['job_card_id'];
        }
        $now = date('Y-m-d H:i:s');
        $db->table('job_cards')->insert([
            'number' => Numbering::next('job_card'), 'branch_id' => $q['branch_id'], 'customer_id' => $q['customer_id'],
            'vehicle_id' => $q['vehicle_id'], 'quote_id' => $quoteId, 'status' => 'booked', 'priority' => 'normal',
            'customer_complaint' => $q['notes'], 'public_token' => self::token(), 'created_by' => session('user_id'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $jobId = (int) $db->insertID();
        LineItems::copy('quote', $quoteId, 'job_card', $jobId);
        $db->table('quotes')->where('id', $quoteId)->update(['status' => 'converted', 'job_card_id' => $jobId, 'updated_at' => $now]);
        Activity::log('job_card', $jobId, 'created', 'Created from quote ' . $q['number']);
        return $jobId;
    }

    public static function jobCardToInvoice(int $jobId): int
    {
        $db  = self::db();
        $job = $db->table('job_cards')->where('id', $jobId)->get()->getRowArray();
        $existing = $db->table('invoices')->where(['job_card_id' => $jobId, 'status !=' => 'void', 'deleted_at' => null])->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $cust = $db->table('customers')->where('id', $job['customer_id'])->get()->getRowArray();
        $terms = (int) ($cust['payment_terms_days'] ?: Settings::get('default_payment_terms', 7));
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('invoices')->insert([
            'number' => Numbering::next('invoice'), 'branch_id' => $job['branch_id'], 'customer_id' => $job['customer_id'],
            'vehicle_id' => $job['vehicle_id'], 'job_card_id' => $jobId, 'quote_id' => $job['quote_id'], 'status' => 'unpaid',
            'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime("+{$terms} days")),
            'notes' => $job['work_performed'], 'terms' => Settings::get('invoice_terms'), 'public_token' => self::token(),
            'created_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $invId = (int) $db->insertID();
        LineItems::copy('job_card', $jobId, 'invoice', $invId);
        self::issueStock($invId);
        Ledger::postInvoice($invId);
        $db->table('job_cards')->where('id', $jobId)->update(['status' => 'invoiced', 'updated_at' => $now]);
        $db->transComplete();
        Activity::log('invoice', $invId, 'created', 'Invoiced from job card ' . $job['number']);
        Activity::log('job_card', $jobId, 'invoiced', 'Invoice created');
        \App\Libraries\JobCosting::snapshot($jobId);
        if (($applied = \App\Controllers\Deposits::applyToInvoice($jobId, $invId)) > 0) {
            Activity::log('invoice', $invId, 'deposit_applied', 'Deposit of ' . money($applied) . ' applied');
        }
        return $invId;
    }

    /** Reduce stock for tracked parts on an invoice (idempotent per invoice). */
    public static function issueStock(int $invoiceId): void
    {
        $db = self::db();
        if ($db->table('stock_movements')->where(['reference_type' => 'invoice', 'reference_id' => $invoiceId])->countAllResults() > 0) {
            return;
        }
        $items = $db->table('line_items li')->select('li.product_id, li.quantity, p.cost_price')
            ->join('products p', 'p.id = li.product_id')
            ->where(['li.document_type' => 'invoice', 'li.document_id' => $invoiceId, 'p.track_stock' => 1])->get()->getResultArray();
        foreach ($items as $it) {
            $db->table('products')->set('qty_on_hand', 'qty_on_hand - ' . (float) $it['quantity'], false)->where('id', $it['product_id'])->update();
            $db->table('stock_movements')->insert([
                'product_id' => $it['product_id'], 'type' => 'out', 'quantity' => -(float) $it['quantity'], 'unit_cost' => $it['cost_price'],
                'reference_type' => 'invoice', 'reference_id' => $invoiceId, 'user_id' => session('user_id'), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ------------------------------------------------------------ payments

    public static function recordPayment(int $invoiceId, float $amount, string $method, array $extra = []): int
    {
        $db  = self::db();
        $inv = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('payments')->insert([
            'number' => Numbering::next('payment'), 'branch_id' => $inv['branch_id'], 'invoice_id' => $invoiceId,
            'customer_id' => $inv['customer_id'], 'amount' => round($amount, 2), 'method' => $method,
            'reference' => $extra['reference'] ?? null, 'paid_at' => $extra['paid_at'] ?? $now, 'status' => 'completed',
            'gateway_txn_id' => $extra['gateway_txn_id'] ?? null, 'gateway_payload' => $extra['gateway_payload'] ?? null,
            'notes' => $extra['notes'] ?? null, 'recorded_by' => session('user_id'), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $pid = (int) $db->insertID();
        self::refreshInvoiceStatus($invoiceId);
        Ledger::postPayment($pid);
        $db->transComplete();
        Activity::log('invoice', $invoiceId, 'payment', 'Payment of ' . money($amount) . ' via ' . strtoupper($method));
        if (in_array($method, ['payfast', 'ozow'], true)) {
            Notify::staff('Online payment received: ' . money($amount), 'Invoice ' . $inv['number'] . ' via ' . strtoupper($method), 'invoices/' . $invoiceId, 'card');
        }

        if ((int) Settings::get('send_payment_receipts', 1) === 1 && ($extra['notify'] ?? true)) {
            self::sendReceipt($pid);
        }
        return $pid;
    }

    public static function refreshInvoiceStatus(int $invoiceId): void
    {
        $db   = self::db();
        $inv  = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if ($inv['status'] === 'void' || $inv['status'] === 'draft') {
            return;
        }
        $paid = (float) $db->table('payments')->selectSum('amount')->where(['invoice_id' => $invoiceId, 'status' => 'completed'])->get()->getRow()->amount;
        $credited = (float) $db->table('credit_notes')->selectSum('total')->where(['invoice_id' => $invoiceId, 'status' => 'issued', 'deleted_at' => null])->get()->getRow()->total;
        $bal  = round((float) $inv['total'] - $paid - $credited, 2);
        $status = $bal <= 0.009 ? 'paid' : (($paid + $credited) > 0 ? 'partial' : 'unpaid');
        $db->table('invoices')->where('id', $invoiceId)->update([
            'amount_paid' => round($paid, 2), 'amount_credited' => round($credited, 2), 'balance_due' => max(0, $bal), 'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------ emails

    public static function vehicleLabel(?array $v): string
    {
        return $v ? trim("{$v['make']} {$v['model']} ({$v['registration']})") : 'your vehicle';
    }

    public static function customerName(array $c): string
    {
        return $c['type'] === 'business' && $c['company_name'] ? $c['company_name'] : trim($c['first_name'] . ' ' . $c['last_name']);
    }

    public static function sendInvoice(int $invoiceId, ?string $to = null, string $template = 'invoice'): bool
    {
        $data = DocData::invoice($invoiceId);
        $inv  = $data['invoice'];
        $to   = $to ?: $data['customer']['email'];
        if (!$to) {
            return false;
        }
        [$subject, $body] = Mailer::template($template, [
            'customer' => self::customerName($data['customer']), 'number' => $inv['number'], 'vehicle' => self::vehicleLabel($data['vehicle']),
            'total' => money($inv['total']), 'balance' => money($inv['balance_due']), 'due_date' => date('d M Y', strtotime($inv['due_date'])),
            'pay_link' => site_url('pay/' . $inv['public_token']),
        ]);
        $body .= view('emails/_pay_button', ['url' => site_url('pay/' . $inv['public_token']), 'label' => 'View & Pay Invoice ' . $inv['number']]);
        $pdf = Pdf::render('pdf/invoice', $data);
        $ok  = Mailer::send($to, $subject, $body, [['content' => $pdf, 'name' => $inv['number'] . '.pdf']], 'invoice', $invoiceId);
        if ($ok && $template === 'invoice') {
            self::db()->table('invoices')->where('id', $invoiceId)->update(['sent_at' => date('Y-m-d H:i:s')]);
        }
        Activity::log('invoice', $invoiceId, $ok ? 'emailed' : 'email_failed', ($template === 'reminder' ? 'Reminder' : 'Invoice') . ' email to ' . $to);
        return $ok;
    }

    public static function sendQuote(int $quoteId, ?string $to = null): bool
    {
        $data = DocData::quote($quoteId);
        $q    = $data['quote'];
        $to   = $to ?: $data['customer']['email'];
        if (!$to) {
            return false;
        }
        $link = site_url('q/' . $q['public_token']);
        [$subject, $body] = Mailer::template('quote', [
            'customer' => self::customerName($data['customer']), 'number' => $q['number'], 'vehicle' => self::vehicleLabel($data['vehicle']),
            'total' => money($q['total']), 'link' => $link,
        ]);
        $body .= view('emails/_pay_button', ['url' => $link, 'label' => 'View & Accept Quote']);
        $pdf = Pdf::render('pdf/quote', $data);
        $ok  = Mailer::send($to, $subject, $body, [['content' => $pdf, 'name' => $q['number'] . '.pdf']], 'quote', $quoteId);
        if ($ok) {
            self::db()->table('quotes')->where('id', $quoteId)->update(['sent_at' => date('Y-m-d H:i:s'), 'status' => $q['status'] === 'draft' ? 'sent' : $q['status']]);
        }
        Activity::log('quote', $quoteId, $ok ? 'emailed' : 'email_failed', 'Quote email to ' . $to);
        return $ok;
    }

    public static function sendReceipt(int $paymentId): bool
    {
        $db = self::db();
        $p  = $db->table('payments')->where('id', $paymentId)->get()->getRowArray();
        $c  = $db->table('customers')->where('id', $p['customer_id'])->get()->getRowArray();
        $i  = $db->table('invoices')->where('id', $p['invoice_id'])->get()->getRowArray();
        if (!$c || !$c['email']) {
            return false;
        }
        [$subject, $body] = Mailer::template('receipt', [
            'customer' => self::customerName($c), 'number' => $p['number'], 'amount' => money($p['amount']),
            'invoice' => $i['number'] ?? '', 'balance' => money($i['balance_due'] ?? 0),
        ]);
        $pdf = Pdf::render('pdf/receipt', ['payment' => $p, 'customer' => $c, 'invoice' => $i]);
        return Mailer::send($c['email'], $subject, $body, [['content' => $pdf, 'name' => $p['number'] . '.pdf']], 'payment', $paymentId);
    }

    public static function sendVehicleReady(int $jobId): bool
    {
        $db  = self::db();
        $job = $db->table('job_cards')->where('id', $jobId)->get()->getRowArray();
        $c   = $db->table('customers')->where('id', $job['customer_id'])->get()->getRowArray();
        $v   = $db->table('vehicles')->where('id', $job['vehicle_id'])->get()->getRowArray();
        if (!$c['email']) {
            return false;
        }
        [$subject, $body] = Mailer::template('ready', ['customer' => self::customerName($c), 'vehicle' => self::vehicleLabel($v), 'number' => $job['number']]);
        return Mailer::send($c['email'], $subject, $body, [], 'job_card', $jobId);
    }
}
