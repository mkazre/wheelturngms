<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 2: credit notes, purchasing & payables, banking & reconciliation, VAT returns.
 */
class CreateAccountingSchema extends Migration
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

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    private function date(): array
    {
        return ['type' => 'DATE', 'null' => true];
    }

    private function totals(): array
    {
        return ['subtotal' => $this->money(), 'discount_total' => $this->money(), 'vat_total' => $this->money(), 'total' => $this->money()];
    }

    private function create(string $table, array $fields, array $keys = [], array $unique = []): void
    {
        $this->forge->addField($fields + ['created_at' => $this->dt(), 'updated_at' => $this->dt()]);
        $this->forge->addPrimaryKey('id');
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        foreach ($unique as $u) {
            $this->forge->addUniqueKey($u);
        }
        $this->forge->createTable($table, true);
    }

    public function up(): void
    {
        // Line items gain an optional ledger account (supplier bills for non-stock purchases)
        $this->forge->addColumn('line_items', ['account_id' => $this->fk()]);
        // Invoices track credits separately from cash payments
        $this->forge->addColumn('invoices', ['amount_credited' => $this->money()]);
        // Bank reconciliation flags on ledger lines
        $this->forge->addColumn('journal_lines', [
            'reconciled'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'bank_txn_id' => $this->fk(),
        ]);
        $this->forge->addColumn('journal_entries', [
            'is_reversal' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'reversed_by' => $this->fk(),
            'memo'        => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addColumn('accounts', ['parent_id' => $this->fk(), 'bank_account_number' => $this->str(40)]);

        $this->create('credit_notes', $this->id() + [
            'number'      => $this->str(30, false),
            'branch_id'   => $this->fk(),
            'invoice_id'  => $this->fk(),
            'customer_id' => $this->fk(false),
            'issue_date'  => $this->date(),
            'reason'      => $this->str(500),
            'restock'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'      => $this->str(20, false), // issued, void
            'created_by'  => $this->fk(),
            'deleted_at'  => $this->dt(),
        ] + $this->totals(), ['invoice_id', 'customer_id'], ['number']);

        $this->create('purchase_orders', $this->id() + [
            'number'        => $this->str(30, false),
            'branch_id'     => $this->fk(),
            'supplier_id'   => $this->fk(false),
            'job_card_id'   => $this->fk(),
            'status'        => $this->str(20, false), // draft, sent, received, billed, cancelled
            'order_date'    => $this->date(),
            'expected_date' => $this->date(),
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'received_at'   => $this->dt(),
            'created_by'    => $this->fk(),
            'deleted_at'    => $this->dt(),
        ] + $this->totals(), ['supplier_id', 'status'], ['number']);

        $this->create('supplier_bills', $this->id() + [
            'number'            => $this->str(30, false),
            'supplier_invoice'  => $this->str(60),
            'branch_id'         => $this->fk(),
            'supplier_id'       => $this->fk(false),
            'purchase_order_id' => $this->fk(),
            'status'            => $this->str(20, false), // unpaid, partial, paid, void
            'bill_date'         => $this->date(),
            'due_date'          => $this->date(),
            'notes'             => ['type' => 'TEXT', 'null' => true],
            'amount_paid'       => $this->money(),
            'balance_due'       => $this->money(),
            'stock_received'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_by'        => $this->fk(),
            'deleted_at'        => $this->dt(),
        ] + $this->totals(), ['supplier_id', 'status', 'due_date'], ['number']);

        $this->create('supplier_payments', $this->id() + [
            'number'       => $this->str(30, false),
            'supplier_id'  => $this->fk(false),
            'bill_id'      => $this->fk(),
            'amount'       => $this->money(),
            'method'       => $this->str(20),
            'paid_from_id' => $this->fk(false),
            'paid_at'      => $this->dt(),
            'reference'    => $this->str(100),
            'status'       => $this->str(20, false),
            'created_by'   => $this->fk(),
        ], ['supplier_id', 'bill_id'], ['number']);

        $this->create('bank_transactions', $this->id() + [
            'account_id'      => $this->fk(false),
            'txn_date'        => $this->date(),
            'description'     => $this->str(255),
            'reference'       => $this->str(100),
            'amount'          => $this->money(), // + money in, - money out
            'running_balance' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'import_batch'    => $this->str(40),
            'fingerprint'     => $this->str(64),
            'status'          => $this->str(20, false), // unmatched, matched, ignored
            'journal_entry_id'=> $this->fk(),
            'matched_by'      => $this->fk(),
            'matched_at'      => $this->dt(),
        ], ['account_id', 'status', 'txn_date'], ['fingerprint']);

        $this->create('vat_returns', $this->id() + [
            'period_start'     => $this->date(),
            'period_end'       => $this->date(),
            'output_base'      => $this->money(),
            'output_vat'       => $this->money(),
            'input_base'       => $this->money(),
            'input_vat'        => $this->money(),
            'net_vat'          => $this->money(),
            'status'           => $this->str(20, false), // draft, submitted, paid
            'submitted_at'     => $this->dt(),
            'paid_at'          => $this->dt(),
            'paid_from_id'     => $this->fk(),
            'sars_reference'   => $this->str(60),
            'created_by'       => $this->fk(),
        ], ['period_start']);
    }

    public function down(): void
    {
        foreach (['vat_returns', 'bank_transactions', 'supplier_payments', 'supplier_bills', 'purchase_orders', 'credit_notes'] as $t) {
            $this->forge->dropTable($t, true);
        }
        $this->forge->dropColumn('line_items', 'account_id');
        $this->forge->dropColumn('invoices', 'amount_credited');
        $this->forge->dropColumn('journal_lines', ['reconciled', 'bank_txn_id']);
        $this->forge->dropColumn('journal_entries', ['is_reversal', 'reversed_by', 'memo']);
        $this->forge->dropColumn('accounts', ['parent_id', 'bank_account_number']);
    }
}
