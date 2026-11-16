<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Installs the minimum data the system needs: main branch, admin user,
 * South African chart of accounts and default settings.
 * Safe to re-run: existing rows are left untouched.
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        if ($this->db->table('branches')->countAllResults() === 0) {
            $this->db->table('branches')->insert([
                'name' => 'Wheelsturn Automotive – Main', 'code' => 'MAIN', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if ($this->db->table('users')->where('role', 'admin')->countAllResults() === 0) {
            $this->db->table('users')->insert([
                'branch_id' => 1, 'role' => 'admin', 'first_name' => 'System', 'last_name' => 'Administrator',
                'email' => 'admin@wheelsturn.co.za', 'job_title' => 'Administrator',
                'password_hash' => password_hash('Admin@12345', PASSWORD_DEFAULT),
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ($this->accounts() as [$code, $name, $type, $sub]) {
            if ($this->db->table('accounts')->where('code', $code)->countAllResults() === 0) {
                $this->db->table('accounts')->insert([
                    'code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $sub,
                    'is_system' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach ($this->settings() as $group => $items) {
            foreach ($items as $key => $value) {
                if ($this->db->table('settings')->where('key', $key)->countAllResults() === 0) {
                    $this->db->table('settings')->insert([
                        'key' => $key, 'value' => (string) $value, 'group' => $group,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /** South African chart of accounts (IFRS for SMEs friendly). */
    private function accounts(): array
    {
        return [
            ['1000', 'Bank – Current Account', 'asset', 'bank'],
            ['1010', 'Petty Cash', 'asset', 'cash'],
            ['1020', 'Card Machine Clearing', 'asset', 'bank'],
            ['1030', 'PayFast Clearing', 'asset', 'bank'],
            ['1040', 'Ozow Clearing', 'asset', 'bank'],
            ['1100', 'Trade Debtors (Accounts Receivable)', 'asset', 'receivable'],
            ['1200', 'Inventory – Parts & Consumables', 'asset', 'inventory'],
            ['1300', 'VAT Input', 'asset', 'vat_input'],
            ['1500', 'Workshop Equipment', 'asset', 'fixed_asset'],
            ['1510', 'Accumulated Depreciation – Equipment', 'asset', 'contra_asset'],
            ['1520', 'Motor Vehicles', 'asset', 'fixed_asset'],
            ['1530', 'Accumulated Depreciation – Vehicles', 'asset', 'contra_asset'],
            ['2000', 'Trade Creditors (Accounts Payable)', 'liability', 'payable'],
            ['2100', 'VAT Output', 'liability', 'vat_output'],
            ['2110', 'VAT Control (SARS)', 'liability', 'vat_control'],
            ['2200', 'PAYE Payable', 'liability', 'payroll'],
            ['2210', 'UIF Payable', 'liability', 'payroll'],
            ['2220', 'SDL Payable', 'liability', 'payroll'],
            ['2300', 'Customer Deposits', 'liability', 'current'],
            ['2500', 'Loans Payable', 'liability', 'non_current'],
            ['3000', "Owner's Capital / Share Capital", 'equity', 'capital'],
            ['3100', 'Retained Earnings', 'equity', 'retained'],
            ['3200', 'Drawings', 'equity', 'drawings'],
            ['4000', 'Sales – Labour', 'income', 'sales'],
            ['4100', 'Sales – Parts', 'income', 'sales'],
            ['4200', 'Sales – Sundries', 'income', 'sales'],
            ['4500', 'Other Income', 'income', 'other'],
            ['5000', 'Cost of Sales – Parts', 'expense', 'cost_of_sales'],
            ['5100', 'Cost of Sales – Sub-contract Work', 'expense', 'cost_of_sales'],
            ['6000', 'Salaries & Wages', 'expense', 'operating'],
            ['6010', 'Rent', 'expense', 'operating'],
            ['6020', 'Electricity & Water', 'expense', 'operating'],
            ['6030', 'Telephone & Internet', 'expense', 'operating'],
            ['6040', 'Insurance', 'expense', 'operating'],
            ['6050', 'Bank Charges', 'expense', 'operating'],
            ['6060', 'Fuel & Oil', 'expense', 'operating'],
            ['6070', 'Repairs & Maintenance', 'expense', 'operating'],
            ['6080', 'Advertising & Marketing', 'expense', 'operating'],
            ['6090', 'Consumables & Workshop Supplies', 'expense', 'operating'],
            ['6100', 'Tools & Small Equipment', 'expense', 'operating'],
            ['6110', 'Accounting & Legal Fees', 'expense', 'operating'],
            ['6120', 'Depreciation', 'expense', 'operating'],
            ['6130', 'Payment Gateway Fees', 'expense', 'operating'],
            ['6140', 'Stationery & Printing', 'expense', 'operating'],
            ['6150', 'Travel & Accommodation', 'expense', 'operating'],
            ['6160', 'Cleaning & Security', 'expense', 'operating'],
            ['6170', 'Software & Subscriptions', 'expense', 'operating'],
            ['6999', 'Sundry Expenses', 'expense', 'operating'],
        ];
    }

    private function settings(): array
    {
        return [
            'company' => [
                'company_name'        => 'Wheelsturn Automotive',
                'company_legal_name'  => 'Wheelsturn Automotive (Pty) Ltd',
                'company_reg_number'  => '',
                'company_vat_number'  => '',
                'company_email'       => 'info@wheelsturn.co.za',
                'company_phone'       => '',
                'company_website'     => '',
                'company_address'     => "Street address\nCity\nProvince, 0000",
                'company_rmi_number'  => '',
                'bank_name'           => '',
                'bank_account_name'   => 'Wheelsturn Automotive',
                'bank_account_number' => '',
                'bank_branch_code'    => '',
                'bank_account_type'   => 'Current',
            ],
            'finance' => [
                'currency'               => 'ZAR',
                'currency_symbol'        => 'R',
                'vat_registered'         => 1,
                'vat_rate'               => 15,
                'prices_include_vat'     => 0,
                'default_labour_rate'    => 650,
                'default_payment_terms'  => 7,
                'quote_validity_days'    => 14,
                'financial_year_start'   => 3, // March (SA tax year)
                'vat_period_months'      => 2,
            ],
            'documents' => [
                'prefix_job_card' => 'JC-',  'next_job_card' => 1001,
                'prefix_quote'    => 'QT-',  'next_quote'    => 1001,
                'prefix_invoice'  => 'INV-', 'next_invoice'  => 1001,
                'prefix_payment'  => 'PAY-', 'next_payment'  => 1001,
                'prefix_expense'  => 'EXP-', 'next_expense'  => 1001,
                'prefix_journal'  => 'JNL-', 'next_journal'  => 1001,
                'prefix_customer' => 'CUS-', 'next_customer' => 1001,
                'prefix_credit_note' => 'CN-', 'next_credit_note' => 1001,
                'prefix_purchase_order' => 'PO-', 'next_purchase_order' => 1001,
                'prefix_bill' => 'BILL-', 'next_bill' => 1001,
                'prefix_supplier_payment' => 'SP-', 'next_supplier_payment' => 1001,
                'lock_date' => '',
                'invoice_terms'   => "Payment due by the date shown. Goods remain the property of Wheelsturn Automotive until paid in full. E&OE.",
                'quote_terms'     => "Quote valid for the period shown. Prices subject to parts availability. Additional work only with customer approval.",
                'job_card_terms'  => "I authorise the above work and road testing of the vehicle. Wheelsturn Automotive is not responsible for loss or damage to vehicles or articles left in vehicles in case of fire, theft or any cause beyond our control. Vehicles not collected within 7 days of completion may incur storage fees.",
                'invoice_footer'  => 'Thank you for choosing Wheelsturn Automotive.',
                'inspection_template' => "Exterior|Body panels & paint\nExterior|Windscreen & glass\nExterior|Lights & indicators\nExterior|Wiper blades\nTyres|Front left tyre\nTyres|Front right tyre\nTyres|Rear left tyre\nTyres|Rear right tyre\nTyres|Spare tyre\nUnder bonnet|Engine oil level & condition\nUnder bonnet|Coolant level\nUnder bonnet|Brake fluid\nUnder bonnet|Battery & terminals\nUnder bonnet|Drive belts\nUnder bonnet|Hoses\nUnder vehicle|Brake pads & discs\nUnder vehicle|Shock absorbers\nUnder vehicle|Exhaust system\nUnder vehicle|CV joints & boots\nUnder vehicle|Oil leaks\nInterior|Warning lights\nInterior|Air conditioning\nInterior|Seat belts\nInterior|Horn",
            ],
            'email' => [
                'mail_protocol'   => 'smtp',
                'smtp_host'       => '',
                'smtp_port'       => 587,
                'smtp_user'       => '',
                'smtp_pass'       => '',
                'smtp_crypto'     => 'tls',
                'mail_from_email' => 'noreply@wheelsturn.co.za',
                'mail_from_name'  => 'Wheelsturn Automotive',
                'mail_bcc'        => '',
                'tpl_invoice_subject' => 'Tax Invoice {number} from {company}',
                'tpl_invoice_body'    => "Hi {customer},\n\nPlease find attached tax invoice {number} for {vehicle} amounting to {total}.\nAmount due: {balance} by {due_date}.\n\nYou can pay securely online here: {pay_link}\n\nThank you for your business.",
                'tpl_quote_subject'   => 'Quotation {number} from {company}',
                'tpl_quote_body'      => "Hi {customer},\n\nPlease find attached quotation {number} for {vehicle} amounting to {total}.\nView, accept or decline online: {link}\n\nKind regards.",
                'tpl_reminder_subject'=> 'Payment reminder: Invoice {number} is due',
                'tpl_reminder_body'   => "Hi {customer},\n\nThis is a friendly reminder that invoice {number} has an outstanding balance of {balance} (due {due_date}).\n\nPay online in seconds: {pay_link}\n\nIf you have already paid, please ignore this message.",
                'tpl_receipt_subject' => 'Payment received – thank you ({number})',
                'tpl_receipt_body'    => "Hi {customer},\n\nWe have received your payment of {amount} for invoice {invoice}. Remaining balance: {balance}.\n\nThank you!",
                'tpl_ready_subject'   => 'Your vehicle {vehicle} is ready for collection',
                'tpl_ready_body'      => "Hi {customer},\n\nGood news! Your vehicle {vehicle} is ready for collection (job card {number}).\n\nSee you soon.",
            ],
            'payments' => [
                'payfast_enabled'      => 0,
                'payfast_sandbox'      => 1,
                'payfast_merchant_id'  => '10000100',
                'payfast_merchant_key' => '46f0cd694581a',
                'payfast_passphrase'   => '',
                'ozow_enabled'         => 0,
                'ozow_test_mode'       => 1,
                'ozow_site_code'       => '',
                'ozow_private_key'     => '',
                'ozow_api_key'         => '',
                'eft_enabled'          => 1,
                'allow_partial_online_payment' => 0,
            ],
            'notifications' => [
                'weekly_report_enabled'    => 1,
                'weekly_report_recipients' => 'admin@wheelsturn.co.za',
                'weekly_report_last_sent'  => '',
                'auto_payment_reminders'   => 1,
                'reminder_days_after_due'  => '3,7,14',
                'notify_vehicle_ready'     => 1,
                'send_payment_receipts'    => 1,
                'service_reminders'        => 1,
                'service_reminder_days'    => 14,
                'licence_reminders'        => 1,
                'feedback_requests'        => 1,
                'google_review_url'        => '',
                'auto_statements'          => 0,
                'sms_reminders'            => 0,
                'workshop_bays'            => 4,
                'backup_enabled'           => 1,
                'sms_provider'             => '',
                'ai_enabled'               => 0,
                'monthly_revenue_target'   => 0,
                'markup_matrix'            => "0-100=120\n100.01-500=80\n500.01-1500=55\n1500.01-5000=40\n5000.01-*=30",
            ],
            'appearance' => [
                'theme_default' => 'light',
                'pdf_accent'    => '#9b1631',
            ],
        ];
    }
}
