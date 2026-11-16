<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Mailer;
use App\Libraries\Settings as S;

class Settings extends BaseController
{
    /**
     * Section => [title, icon, description, fields]. Field: key => [label, type, help, options]
     * Types: text, email, number, textarea, toggle, select, password, color.
     */
    public static function sections(): array
    {
        $provinces = ['3' => 'March (SA tax year)', '1' => 'January', '7' => 'July'];
        return [
            'company' => ['Company profile', 'building', 'Appears on every invoice, quote, job card, statement and email.', [
                'company_name' => ['Trading name', 'text'], 'company_legal_name' => ['Registered (legal) name', 'text'],
                'company_reg_number' => ['CIPC registration number', 'text', 'e.g. 2021/123456/07'], 'company_vat_number' => ['VAT number', 'text', 'Required on SARS tax invoices'],
                'company_rmi_number' => ['RMI / MIWA member number', 'text'], 'company_email' => ['Email', 'email'], 'company_phone' => ['Phone', 'text'],
                'company_website' => ['Website', 'text'], 'company_address' => ['Physical address', 'textarea'],
                'bank_name' => ['Bank', 'text'], 'bank_account_name' => ['Account holder', 'text'], 'bank_account_number' => ['Account number', 'text'],
                'bank_branch_code' => ['Branch code', 'text'], 'bank_account_type' => ['Account type', 'select', null, ['Current' => 'Current / cheque', 'Business' => 'Business', 'Savings' => 'Savings']],
            ]],
            'finance' => ['Tax & finance', 'shield', 'VAT, labour rates and payment terms (South Africa).', [
                'vat_registered' => ['VAT registered', 'toggle', 'Show VAT and issue "Tax invoices"'], 'vat_rate' => ['VAT rate (%)', 'number'],
                'vat_period_months' => ['VAT period', 'select', null, ['1' => 'Monthly (Category C)', '2' => 'Every 2 months (Category A/B)', '6' => 'Every 6 months (Category D)']],
                'financial_year_start' => ['Financial year starts', 'select', null, $provinces],
                'default_labour_rate' => ['Default labour rate (R/hour, excl. VAT)', 'number'], 'default_payment_terms' => ['Default payment terms (days)', 'number'],
                'quote_validity_days' => ['Quotes valid for (days)', 'number'], 'currency_symbol' => ['Currency symbol', 'text'],
                'monthly_revenue_target' => ['Monthly revenue target (R, excl. VAT)', 'number', 'Shown as a progress bar on the dashboard'],
            ]],
            'documents' => ['Documents & numbering', 'file', 'Prefixes, next numbers, terms and the inspection checklist.', [
                'prefix_job_card' => ['Job card prefix', 'text'], 'next_job_card' => ['Next job card no.', 'number'],
                'prefix_quote' => ['Quote prefix', 'text'], 'next_quote' => ['Next quote no.', 'number'],
                'prefix_invoice' => ['Invoice prefix', 'text'], 'next_invoice' => ['Next invoice no.', 'number', 'SARS requires sequential, unique numbers. Only increase.'],
                'prefix_payment' => ['Receipt prefix', 'text'], 'next_payment' => ['Next receipt no.', 'number'],
                'prefix_expense' => ['Expense prefix', 'text'], 'prefix_customer' => ['Customer code prefix', 'text'],
                'invoice_terms' => ['Invoice terms', 'textarea'], 'quote_terms' => ['Quote terms', 'textarea'], 'job_card_terms' => ['Job card authorisation & disclaimer', 'textarea'],
                'invoice_footer' => ['Invoice footer line', 'text'],
                'inspection_template' => ['Inspection checklist (Section|Item per line)', 'textarea', 'Used for every new job card\'s digital vehicle inspection.'],
            ]],
            'email' => ['Email & SMTP', 'mail', 'Outgoing mail server and message templates. cPanel email accounts work well.', [
                'smtp_host' => ['SMTP host', 'text', 'e.g. mail.yourdomain.co.za'], 'smtp_port' => ['Port', 'number', '465 (SSL) or 587 (TLS)'],
                'smtp_crypto' => ['Encryption', 'select', null, ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'None']],
                'smtp_user' => ['Username', 'text'], 'smtp_pass' => ['Password', 'password', 'Stored encrypted'],
                'mail_from_email' => ['From address', 'email'], 'mail_from_name' => ['From name', 'text'], 'mail_bcc' => ['BCC all customer emails to', 'email'],
                'tpl_invoice_subject' => ['Invoice email: subject', 'text'], 'tpl_invoice_body' => ['Invoice email: body', 'textarea', 'Placeholders: {customer} {number} {vehicle} {total} {balance} {due_date} {pay_link} {company}'],
                'tpl_reminder_subject' => ['Payment reminder: subject', 'text'], 'tpl_reminder_body' => ['Payment reminder: body', 'textarea'],
                'tpl_receipt_subject' => ['Payment receipt: subject', 'text'], 'tpl_receipt_body' => ['Payment receipt: body', 'textarea', 'Placeholders: {customer} {amount} {invoice} {balance} {number}'],
                'tpl_quote_subject' => ['Quote email: subject', 'text'], 'tpl_quote_body' => ['Quote email: body', 'textarea', 'Placeholders: {customer} {number} {vehicle} {total} {link}'],
                'tpl_ready_subject' => ['Vehicle ready: subject', 'text'], 'tpl_ready_body' => ['Vehicle ready: body', 'textarea'],
            ]],
            'payments' => ['Online payments', 'card', 'Let customers pay invoices online with PayFast and Ozow.', [
                'payfast_enabled' => ['Enable PayFast', 'toggle', 'Card, Instant EFT, SnapScan, Zapper, Mobicred…'], 'payfast_sandbox' => ['PayFast sandbox (test) mode', 'toggle'],
                'payfast_merchant_id' => ['PayFast merchant ID', 'text'], 'payfast_merchant_key' => ['PayFast merchant key', 'password'],
                'payfast_passphrase' => ['PayFast passphrase', 'password', 'Must match the passphrase set in your PayFast dashboard'],
                'ozow_enabled' => ['Enable Ozow', 'toggle', 'Instant EFT straight from the customer\'s bank'], 'ozow_test_mode' => ['Ozow test mode', 'toggle'],
                'ozow_site_code' => ['Ozow site code', 'text'], 'ozow_private_key' => ['Ozow private key', 'password'], 'ozow_api_key' => ['Ozow API key (optional, for verification)', 'password'],
                'eft_enabled' => ['Show EFT banking details on pay page', 'toggle'], 'allow_partial_online_payment' => ['Allow partial online payments', 'toggle'],
                'send_payment_receipts' => ['Email a receipt automatically on payment', 'toggle'],
            ]],
            'notifications' => ['Notifications & automation', 'bell', 'Monday report, reminders and customer updates.', [
                'weekly_report_enabled' => ['Send Monday morning performance report', 'toggle'],
                'weekly_report_recipients' => ['Report recipients (comma separated)', 'text', 'e.g. owner@wheelsturn.co.za, manager@wheelsturn.co.za'],
                'auto_payment_reminders' => ['Automatic overdue payment reminders', 'toggle'],
                'reminder_days_after_due' => ['Remind on these days after due date', 'text', 'Comma separated, e.g. 3,7,14'],
                'notify_vehicle_ready' => ['Email customer when vehicle is ready', 'toggle'],
                'service_reminders' => ['Automatic service-due reminders', 'toggle', 'Sent to customers who opted in to reminders'],
                'service_reminder_days' => ['Send service reminder this many days before due', 'number'],
                'licence_reminders' => ['Licence disc renewal reminders (21 days before expiry)', 'toggle'],
                'feedback_requests' => ['Ask for feedback the day after collection (NPS)', 'toggle'],
                'google_review_url' => ['Google review link', 'text', 'Happy customers (9–10) are invited to review you here'],
                'auto_statements' => ['Email statements to customers with balances on the 1st of each month', 'toggle'],
                'sms_reminders' => ['Also send reminders by SMS', 'toggle', 'Requires an SMS provider below'],
                'workshop_bays' => ['Number of workshop bays / lifts', 'number'],
                'backup_enabled' => ['Nightly database backup', 'toggle', 'Kept in writable/backups (last 14 days)'],
            ]],
            'ai' => ['AI assistant', 'zap', 'Claude-powered diagnosis help, fault-code explanations, customer summaries and service suggestions. Only vehicle and job details are sent, never customer names or contact details (POPIA).', [
                'ai_enabled' => ['Enable the AI assistant', 'toggle'],
                'ai_api_key' => ['Anthropic API key', 'password', 'Create one at console.anthropic.com → API keys. Usage is billed by Anthropic to your account.'],
            ]],
            'sms' => ['SMS gateway', 'phone', 'Send reminders and campaigns by SMS via BulkSMS or Clickatell (South African coverage).', [
                'sms_provider' => ['Provider', 'select', null, ['' => 'Disabled', 'bulksms' => 'BulkSMS', 'clickatell' => 'Clickatell']],
                'sms_username' => ['Username / token ID (BulkSMS)', 'text'],
                'sms_password' => ['Password / API key', 'password', 'BulkSMS token secret, or Clickatell API key'],
                'sms_sender' => ['Sender ID (if approved)', 'text'],
            ]],
        ];
    }

    public function index(string $section = 'company')
    {
        $sections = self::sections();
        if ($section === 'branches') {
            return view('settings/branches', ['sections' => $sections, 'section' => $section, 'branches' => $this->db->table('branches')->orderBy('id')->get()->getResultArray()]);
        }
        if ($section === 'system') {
            return view('settings/system', ['sections' => $sections, 'section' => $section, 'emailLog' => $this->db->table('email_logs')->orderBy('id', 'DESC')->limit(25)->get()->getResultArray()]);
        }
        if (!isset($sections[$section])) {
            return $this->notFound('Settings section');
        }
        return view('settings/index', ['sections' => $sections, 'section' => $section, 'values' => S::all()]);
    }

    public function save(string $section)
    {
        $sections = self::sections();
        if (!isset($sections[$section])) {
            return $this->notFound('Settings section');
        }
        foreach ($sections[$section][3] as $key => $f) {
            $type = $f[1];
            if ($type === 'toggle') {
                S::set($key, $this->request->getPost($key) ? '1' : '0', $section);
                continue;
            }
            $v = $this->request->getPost($key);
            if ($v === null) {
                continue;
            }
            if ($type === 'password' && $v === '') {
                continue; // keep existing secret
            }
            if (str_starts_with($key, 'next_')) {
                $current = (int) S::get($key, 0);
                if ((int) $v < $current && $key === 'next_invoice') {
                    return redirect()->back()->with('error', 'Invoice numbers cannot go backwards (SARS sequential numbering).');
                }
            }
            S::set($key, is_string($v) ? trim($v) : $v, $section);
        }
        S::flush();
        Activity::log('settings', null, 'updated', 'Updated ' . $sections[$section][0] . ' settings');
        return redirect()->to(site_url('settings/' . $section))->with('success', $sections[$section][0] . ' saved.');
    }

    public function testEmail()
    {
        $to = $this->input('to', auth_user('email'));
        $ok = Mailer::send($to, 'Test email from ' . setting('company_name'), '<p>Your SMTP settings work. Invoices, quotes, reminders and the Monday report will be delivered from this server.</p>', [], 'system', null);
        $err = $ok ? '' : ($this->db->table('email_logs')->orderBy('id', 'DESC')->limit(1)->get()->getRow()->error ?? '');
        return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to : 'Test failed: ' . mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($err))), 0, 300));
    }

    public function saveBranch()
    {
        $id = (int) $this->request->getPost('id');
        $data = $this->only(['name', 'code', 'address', 'phone', 'email']) + ['is_active' => $this->request->getPost('is_active') ? 1 : 0, 'updated_at' => $this->now()];
        if (!$data['name']) {
            return redirect()->back()->with('error', 'Branch name is required.');
        }
        if ($id) {
            $this->db->table('branches')->where('id', $id)->update($data);
        } else {
            $this->db->table('branches')->insert($data + ['created_at' => $this->now()]);
        }
        return redirect()->to(site_url('settings/branches'))->with('success', 'Branch saved.');
    }
}
