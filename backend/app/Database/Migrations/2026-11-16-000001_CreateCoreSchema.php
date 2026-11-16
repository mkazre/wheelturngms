<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Wheelsturn GMS core schema (Phase 1).
 * Money columns are DECIMAL(15,2); quantities DECIMAL(12,3).
 */
class CreateCoreSchema extends Migration
{
    private function id(): array
    {
        return ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
    }

    private function fk(bool $null = true): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $null];
    }

    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
    }

    private function str(int $len = 191, bool $null = true): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
    }

    private function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    private function date(): array
    {
        return ['type' => 'DATE', 'null' => true];
    }

    private function ts(bool $soft = false): array
    {
        $f = ['created_at' => $this->dt(), 'updated_at' => $this->dt()];
        if ($soft) {
            $f['deleted_at'] = $this->dt();
        }
        return $f;
    }

    private function create(string $table, array $fields, array $keys = [], array $unique = []): void
    {
        $this->forge->addField($fields);
        $this->forge->addPrimaryKey('id');
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        foreach ($unique as $u) {
            $this->forge->addUniqueKey($u);
        }
        $this->forge->createTable($table, true);
    }

    /** Shared header columns for quotes / invoices. */
    private function documentTotals(): array
    {
        return [
            'subtotal'       => $this->money(),
            'discount_total' => $this->money(),
            'vat_total'      => $this->money(),
            'total'          => $this->money(),
        ];
    }

    public function up(): void
    {
        $this->create('branches', $this->id() + [
            'name'      => $this->str(150, false),
            'code'      => $this->str(20),
            'address'   => $this->text(),
            'phone'     => $this->str(50),
            'email'     => $this->str(),
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->ts());

        $this->create('users', $this->id() + [
            'branch_id'        => $this->fk(),
            'customer_id'      => $this->fk(),
            'role'             => $this->str(30, false),
            'first_name'       => $this->str(100, false),
            'last_name'        => $this->str(100),
            'email'            => $this->str(191, false),
            'phone'            => $this->str(50),
            'password_hash'    => $this->str(255, false),
            'avatar'           => $this->str(255),
            'job_title'        => $this->str(100),
            'hourly_rate'      => $this->money(),
            'cost_rate'        => $this->money(),
            'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_login_at'    => $this->dt(),
            'reset_token'      => $this->str(100),
            'reset_expires_at' => $this->dt(),
            'push_token'       => $this->str(255),
        ] + $this->ts(true), ['role', 'branch_id', 'customer_id'], ['email']);

        $this->create('settings', $this->id() + [
            'key'   => $this->str(100, false),
            'value' => ['type' => 'TEXT', 'null' => true],
            'group' => $this->str(50),
        ] + $this->ts(), ['group'], ['key']);

        $this->create('customers', $this->id() + [
            'branch_id'          => $this->fk(),
            'code'               => $this->str(30),
            'type'               => $this->str(20, false),
            'first_name'         => $this->str(100),
            'last_name'          => $this->str(100),
            'company_name'       => $this->str(191),
            'vat_number'         => $this->str(30),
            'registration_number'=> $this->str(50),
            'id_number'          => $this->str(30),
            'email'              => $this->str(),
            'phone'              => $this->str(50),
            'alt_phone'          => $this->str(50),
            'address_line1'      => $this->str(),
            'address_line2'      => $this->str(),
            'city'               => $this->str(100),
            'province'           => $this->str(100),
            'postal_code'        => $this->str(10),
            'payment_terms_days' => ['type' => 'INT', 'default' => 0],
            'credit_limit'       => $this->money(),
            'source'             => $this->str(50),
            'tags'               => $this->str(255),
            'notes'              => $this->text(),
            'marketing_consent'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'popia_consent_at'   => $this->dt(),
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->ts(true), ['branch_id', 'email', 'phone'], ['code']);

        $this->create('vehicles', $this->id() + [
            'customer_id'       => $this->fk(false),
            'registration'      => $this->str(20, false),
            'vin'               => $this->str(40),
            'make'              => $this->str(60),
            'model'             => $this->str(80),
            'variant'           => $this->str(100),
            'year'              => ['type' => 'SMALLINT', 'null' => true],
            'colour'            => $this->str(40),
            'engine_number'     => $this->str(50),
            'engine_size'       => $this->str(20),
            'fuel_type'         => $this->str(20),
            'transmission'      => $this->str(20),
            'odometer'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'licence_expiry'    => $this->date(),
            'next_service_date' => $this->date(),
            'next_service_km'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes'             => $this->text(),
        ] + $this->ts(true), ['customer_id', 'registration', 'vin']);

        $this->create('suppliers', $this->id() + [
            'name'               => $this->str(191, false),
            'contact_person'     => $this->str(100),
            'email'              => $this->str(),
            'phone'              => $this->str(50),
            'vat_number'         => $this->str(30),
            'address'            => $this->text(),
            'account_number'     => $this->str(50),
            'payment_terms_days' => ['type' => 'INT', 'default' => 30],
            'notes'              => $this->text(),
        ] + $this->ts(true));

        $this->create('products', $this->id() + [
            'sku'           => $this->str(60),
            'barcode'       => $this->str(60),
            'name'          => $this->str(191, false),
            'description'   => $this->text(),
            'type'          => $this->str(20, false), // part, labour, service, fluid, tyre, sundry
            'category'      => $this->str(80),
            'brand'         => $this->str(80),
            'unit'          => $this->str(20),
            'cost_price'    => $this->money(),
            'sell_price'    => $this->money(),
            'vat_rate'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 15],
            'track_stock'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'qty_on_hand'   => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'reorder_level' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'bin_location'  => $this->str(40),
            'supplier_id'   => $this->fk(),
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->ts(true), ['type', 'category', 'supplier_id'], ['sku']);

        $this->create('stock_movements', $this->id() + [
            'product_id'     => $this->fk(false),
            'branch_id'      => $this->fk(),
            'type'           => $this->str(20, false), // in, out, adjust
            'quantity'       => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'unit_cost'      => $this->money(),
            'reference_type' => $this->str(40),
            'reference_id'   => $this->fk(),
            'note'           => $this->str(255),
            'user_id'        => $this->fk(),
            'created_at'     => $this->dt(),
        ], ['product_id', ['reference_type', 'reference_id']]);

        $this->create('job_cards', $this->id() + [
            'number'             => $this->str(30, false),
            'branch_id'          => $this->fk(),
            'customer_id'        => $this->fk(false),
            'vehicle_id'         => $this->fk(false),
            'quote_id'           => $this->fk(),
            'status'             => $this->str(30, false),
            'priority'           => $this->str(20),
            'job_type'           => $this->str(50),
            'advisor_id'         => $this->fk(),
            'bay'                => $this->str(30),
            'odometer_in'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'odometer_out'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'fuel_level'         => $this->str(10),
            'customer_complaint' => $this->text(),
            'diagnosis'          => $this->text(),
            'work_performed'     => $this->text(),
            'recommendations'    => $this->text(),
            'internal_notes'     => $this->text(),
            'booked_for'         => $this->dt(),
            'promised_at'        => $this->dt(),
            'checked_in_at'      => $this->dt(),
            'started_at'         => $this->dt(),
            'completed_at'       => $this->dt(),
            'collected_at'       => $this->dt(),
            'customer_signature' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'signed_at'          => $this->dt(),
            'public_token'       => $this->str(64),
            'created_by'         => $this->fk(),
        ] + $this->documentTotals() + $this->ts(true), ['branch_id', 'customer_id', 'vehicle_id', 'status'], ['number']);

        $this->create('job_card_mechanics', $this->id() + [
            'job_card_id' => $this->fk(false),
            'user_id'     => $this->fk(false),
            'created_at'  => $this->dt(),
        ], ['job_card_id', 'user_id']);

        $this->create('time_logs', $this->id() + [
            'job_card_id' => $this->fk(false),
            'user_id'     => $this->fk(false),
            'started_at'  => $this->dt(),
            'ended_at'    => $this->dt(),
            'minutes'     => ['type' => 'INT', 'default' => 0],
            'note'        => $this->str(255),
        ] + $this->ts(), ['job_card_id', 'user_id']);

        $this->create('inspections', $this->id() + [
            'job_card_id' => $this->fk(false),
            'section'     => $this->str(60),
            'item'        => $this->str(150, false),
            'status'      => $this->str(20), // ok, advisory, urgent, na
            'note'        => $this->str(500),
            'sort_order'  => ['type' => 'INT', 'default' => 0],
        ] + $this->ts(), ['job_card_id']);

        $this->create('quotes', $this->id() + [
            'number'       => $this->str(30, false),
            'branch_id'    => $this->fk(),
            'customer_id'  => $this->fk(false),
            'vehicle_id'   => $this->fk(),
            'job_card_id'  => $this->fk(),
            'status'       => $this->str(20, false), // draft, sent, accepted, declined, expired, converted
            'issue_date'   => $this->date(),
            'expiry_date'  => $this->date(),
            'notes'        => $this->text(),
            'terms'        => $this->text(),
            'public_token' => $this->str(64),
            'sent_at'      => $this->dt(),
            'accepted_at'  => $this->dt(),
            'declined_at'  => $this->dt(),
            'created_by'   => $this->fk(),
        ] + $this->documentTotals() + $this->ts(true), ['customer_id', 'status', 'job_card_id'], ['number']);

        $this->create('invoices', $this->id() + [
            'number'       => $this->str(30, false),
            'branch_id'    => $this->fk(),
            'customer_id'  => $this->fk(false),
            'vehicle_id'   => $this->fk(),
            'job_card_id'  => $this->fk(),
            'quote_id'     => $this->fk(),
            'status'       => $this->str(20, false), // draft, unpaid, partial, paid, void
            'issue_date'   => $this->date(),
            'due_date'     => $this->date(),
            'order_number' => $this->str(60),
            'notes'        => $this->text(),
            'terms'        => $this->text(),
            'amount_paid'  => $this->money(),
            'balance_due'  => $this->money(),
            'cost_total'   => $this->money(),
            'public_token' => $this->str(64),
            'sent_at'      => $this->dt(),
            'voided_at'    => $this->dt(),
            'created_by'   => $this->fk(),
        ] + $this->documentTotals() + $this->ts(true), ['customer_id', 'status', 'issue_date', 'job_card_id'], ['number']);

        // Shared line items for job cards, quotes and invoices
        $this->create('line_items', $this->id() + [
            'document_type'    => $this->str(20, false), // job_card, quote, invoice
            'document_id'      => $this->fk(false),
            'item_type'        => $this->str(20, false), // labour, part, sundry, ...
            'product_id'       => $this->fk(),
            'description'      => $this->str(500, false),
            'quantity'         => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 1],
            'unit_cost'        => $this->money(),
            'unit_price'       => $this->money(),
            'discount_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'vat_rate'         => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 15],
            'line_subtotal'    => $this->money(),
            'line_discount'    => $this->money(),
            'line_vat'         => $this->money(),
            'line_total'       => $this->money(),
            'mechanic_id'      => $this->fk(),
            'sort_order'       => ['type' => 'INT', 'default' => 0],
        ] + $this->ts(), [['document_type', 'document_id'], 'product_id']);

        $this->create('payments', $this->id() + [
            'number'          => $this->str(30, false),
            'branch_id'       => $this->fk(),
            'invoice_id'      => $this->fk(),
            'customer_id'     => $this->fk(false),
            'amount'          => $this->money(),
            'method'          => $this->str(20, false), // cash, card, eft, payfast, ozow, other
            'reference'       => $this->str(100),
            'paid_at'         => $this->dt(),
            'status'          => $this->str(20, false), // completed, pending, failed
            'gateway_txn_id'  => $this->str(100),
            'gateway_payload' => $this->text(),
            'notes'           => $this->text(),
            'recorded_by'     => $this->fk(),
        ] + $this->ts(), ['invoice_id', 'customer_id', 'paid_at', 'gateway_txn_id'], ['number']);

        $this->create('expenses', $this->id() + [
            'number'          => $this->str(30, false),
            'branch_id'       => $this->fk(),
            'expense_date'    => $this->date(),
            'supplier_id'     => $this->fk(),
            'payee'           => $this->str(191),
            'account_id'      => $this->fk(false),
            'paid_from_id'    => $this->fk(),
            'description'     => $this->str(500),
            'amount_excl'     => $this->money(),
            'vat_amount'      => $this->money(),
            'total'           => $this->money(),
            'payment_method'  => $this->str(20),
            'reference'       => $this->str(100),
            'is_vat_claimable'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by'      => $this->fk(),
        ] + $this->ts(true), ['expense_date', 'account_id', 'supplier_id'], ['number']);

        // Chart of accounts / general ledger (double entry)
        $this->create('accounts', $this->id() + [
            'code'        => $this->str(20, false),
            'name'        => $this->str(150, false),
            'type'        => $this->str(20, false), // asset, liability, equity, income, expense
            'sub_type'    => $this->str(40),
            'description' => $this->str(255),
            'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $this->ts(), ['type'], ['code']);

        $this->create('journal_entries', $this->id() + [
            'number'      => $this->str(30, false),
            'entry_date'  => $this->date(),
            'reference'   => $this->str(100),
            'description' => $this->str(500),
            'source_type' => $this->str(30),
            'source_id'   => $this->fk(),
            'branch_id'   => $this->fk(),
            'created_by'  => $this->fk(),
        ] + $this->ts(), ['entry_date', ['source_type', 'source_id']], ['number']);

        $this->create('journal_lines', $this->id() + [
            'journal_entry_id' => $this->fk(false),
            'account_id'       => $this->fk(false),
            'debit'            => $this->money(),
            'credit'           => $this->money(),
            'description'      => $this->str(255),
            'customer_id'      => $this->fk(),
            'supplier_id'      => $this->fk(),
        ], ['journal_entry_id', 'account_id']);

        $this->create('attachments', $this->id() + [
            'entity_type'   => $this->str(30, false),
            'entity_id'     => $this->fk(false),
            'category'      => $this->str(30), // slip, photo, document, invoice, other
            'original_name' => $this->str(255, false),
            'file_path'     => $this->str(255, false),
            'mime'          => $this->str(100),
            'size'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'note'          => $this->str(255),
            'uploaded_by'   => $this->fk(),
            'created_at'    => $this->dt(),
        ], [['entity_type', 'entity_id']]);

        $this->create('activity_logs', $this->id() + [
            'user_id'     => $this->fk(),
            'entity_type' => $this->str(30),
            'entity_id'   => $this->fk(),
            'action'      => $this->str(50, false),
            'description' => $this->str(500),
            'ip_address'  => $this->str(45),
            'created_at'  => $this->dt(),
        ], [['entity_type', 'entity_id'], 'user_id']);

        $this->create('email_logs', $this->id() + [
            'recipient'   => $this->str(255, false),
            'subject'     => $this->str(255),
            'entity_type' => $this->str(30),
            'entity_id'   => $this->fk(),
            'status'      => $this->str(20, false),
            'error'       => $this->text(),
            'created_at'  => $this->dt(),
        ], [['entity_type', 'entity_id']]);
    }

    public function down(): void
    {
        foreach ([
            'email_logs', 'activity_logs', 'attachments', 'journal_lines', 'journal_entries', 'accounts',
            'expenses', 'payments', 'line_items', 'invoices', 'quotes', 'inspections', 'time_logs',
            'job_card_mechanics', 'job_cards', 'stock_movements', 'products', 'suppliers', 'vehicles',
            'customers', 'settings', 'users', 'branches',
        ] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
