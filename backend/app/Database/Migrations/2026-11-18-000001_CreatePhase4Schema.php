<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 4: bookings, canned jobs, checklists, loan cars, DVI approvals, feedback, tasks, notifications,
 * campaigns, deposits, stock takes, warranty, 2FA, ownership history.
 */
class CreatePhase4Schema extends Migration
{
    private function id(): array
    {
        return ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
    }

    private function fk(bool $null = true): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $null];
    }

    private function str(int $len = 191, bool $null = true): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
    }

    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0];
    }

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    private function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    private function bool(int $default = 0): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];
    }

    private function create(string $table, array $fields, array $keys = []): void
    {
        $this->forge->addField($fields + ['created_at' => $this->dt(), 'updated_at' => $this->dt()]);
        $this->forge->addPrimaryKey('id');
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        $this->forge->createTable($table, true);
    }

    public function up(): void
    {
        $this->create('bookings', $this->id() + [
            'branch_id' => $this->fk(), 'customer_id' => $this->fk(), 'vehicle_id' => $this->fk(), 'job_card_id' => $this->fk(),
            'name' => $this->str(150), 'phone' => $this->str(50), 'email' => $this->str(), 'registration' => $this->str(20), 'vehicle_desc' => $this->str(150),
            'service' => $this->str(150), 'preferred_at' => $this->dt(), 'notes' => $this->text(),
            'status' => $this->str(20, false), // pending, confirmed, declined, converted
            'source' => $this->str(20), 'token' => $this->str(64), 'reminder_sent_at' => $this->dt(),
        ], ['status', 'preferred_at']);

        $this->create('canned_jobs', $this->id() + [
            'name' => $this->str(150, false), 'job_type' => $this->str(60), 'description' => $this->text(),
            'labour_hours' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0], 'is_active' => $this->bool(1), 'sort_order' => ['type' => 'INT', 'default' => 0],
        ]);

        $this->create('checklist_templates', $this->id() + ['name' => $this->str(150, false), 'job_type' => $this->str(60), 'items' => $this->text(), 'is_active' => $this->bool(1)]);
        $this->create('job_checklist_items', $this->id() + [
            'job_card_id' => $this->fk(false), 'item' => $this->str(255, false), 'is_done' => $this->bool(), 'done_by' => $this->fk(), 'done_at' => $this->dt(), 'sort_order' => ['type' => 'INT', 'default' => 0],
        ], ['job_card_id']);

        $this->create('loan_vehicles', $this->id() + ['registration' => $this->str(20, false), 'description' => $this->str(150), 'status' => $this->str(20, false), 'odometer' => ['type' => 'INT', 'null' => true], 'notes' => $this->text(), 'is_active' => $this->bool(1)]);
        $this->create('loan_bookings', $this->id() + [
            'loan_vehicle_id' => $this->fk(false), 'customer_id' => $this->fk(false), 'job_card_id' => $this->fk(),
            'out_at' => $this->dt(), 'due_at' => $this->dt(), 'returned_at' => $this->dt(),
            'odometer_out' => ['type' => 'INT', 'null' => true], 'odometer_in' => ['type' => 'INT', 'null' => true],
            'fuel_out' => $this->str(10), 'fuel_in' => $this->str(10), 'licence_number' => $this->str(40), 'notes' => $this->text(), 'created_by' => $this->fk(),
        ], ['loan_vehicle_id', 'customer_id']);

        $this->create('feedback', $this->id() + [
            'job_card_id' => $this->fk(), 'customer_id' => $this->fk(false), 'token' => $this->str(64), 'score' => ['type' => 'TINYINT', 'null' => true],
            'comment' => $this->text(), 'sent_at' => $this->dt(), 'submitted_at' => $this->dt(),
        ], ['customer_id', 'token']);

        $this->create('tasks', $this->id() + [
            'title' => $this->str(255, false), 'notes' => $this->text(), 'due_at' => $this->dt(), 'assigned_to' => $this->fk(),
            'entity_type' => $this->str(30), 'entity_id' => $this->fk(), 'status' => $this->str(20, false), 'priority' => $this->str(20),
            'created_by' => $this->fk(), 'completed_at' => $this->dt(),
        ], ['assigned_to', 'status', 'due_at']);

        $this->create('notifications', $this->id() + [
            'user_id' => $this->fk(), 'role' => $this->str(30), 'title' => $this->str(191, false), 'body' => $this->str(500), 'url' => $this->str(255),
            'icon' => $this->str(30), 'read_at' => $this->dt(),
        ], ['user_id', 'read_at']);

        $this->create('campaigns', $this->id() + [
            'name' => $this->str(150, false), 'segment' => $this->str(40, false), 'channel' => $this->str(20), 'subject' => $this->str(191), 'body' => $this->text(),
            'recipients' => ['type' => 'INT', 'default' => 0], 'sent_count' => ['type' => 'INT', 'default' => 0], 'status' => $this->str(20), 'sent_at' => $this->dt(), 'created_by' => $this->fk(),
        ]);

        $this->create('customer_deposits', $this->id() + [
            'number' => $this->str(30, false), 'customer_id' => $this->fk(false), 'job_card_id' => $this->fk(), 'quote_id' => $this->fk(),
            'amount' => $this->money(), 'method' => $this->str(20), 'reference' => $this->str(100), 'received_at' => $this->dt(),
            'applied_invoice_id' => $this->fk(), 'applied_payment_id' => $this->fk(), 'applied_at' => $this->dt(),
            'status' => $this->str(20, false), 'created_by' => $this->fk(),
        ], ['customer_id', 'job_card_id']);

        $this->create('stock_takes', $this->id() + [
            'number' => $this->str(30, false), 'status' => $this->str(20, false), 'category' => $this->str(80), 'notes' => $this->text(),
            'posted_at' => $this->dt(), 'variance_value' => $this->money(), 'created_by' => $this->fk(),
        ]);
        $this->create('stock_take_lines', $this->id() + [
            'stock_take_id' => $this->fk(false), 'product_id' => $this->fk(false),
            'system_qty' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0], 'counted_qty' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true],
            'unit_cost' => $this->money(),
        ], ['stock_take_id']);

        $this->create('vehicle_owners', $this->id() + ['vehicle_id' => $this->fk(false), 'customer_id' => $this->fk(false), 'from_date' => ['type' => 'DATE', 'null' => true], 'to_date' => ['type' => 'DATE', 'null' => true], 'note' => $this->str(255)], ['vehicle_id']);

        $this->create('ai_conversations', $this->id() + ['user_id' => $this->fk(), 'job_card_id' => $this->fk(), 'title' => $this->str(191), 'messages' => ['type' => 'MEDIUMTEXT', 'null' => true]], ['user_id']);

        // Column additions
        $this->forge->addColumn('inspections', [
            'estimate' => $this->money(), 'customer_decision' => $this->str(20), 'decided_at' => $this->dt(), 'followed_up_at' => $this->dt(),
        ]);
        $this->forge->addColumn('job_cards', [
            'damage_marks' => $this->text(), 'loan_vehicle_id' => $this->fk(), 'labour_cost' => $this->money(), 'parts_cost' => $this->money(),
            'warranty_claim' => $this->bool(), 'canned_job_id' => $this->fk(),
        ]);
        $this->forge->addColumn('line_items', ['warranty_months' => ['type' => 'SMALLINT', 'null' => true]]);
        $this->forge->addColumn('products', ['warranty_months' => ['type' => 'SMALLINT', 'null' => true], 'labour_hours' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true]]);
        $this->forge->addColumn('users', ['totp_secret' => $this->str(64), 'totp_enabled' => $this->bool(), 'commission_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0], 'monthly_target' => $this->money()]);
        $this->forge->addColumn('vehicles', ['service_reminder_sent_at' => $this->dt(), 'licence_reminder_sent_at' => $this->dt()]);
        $this->forge->addColumn('customers', ['sms_consent' => $this->bool(1), 'last_statement_sent_at' => $this->dt()]);
    }

    public function down(): void
    {
        foreach (['ai_conversations', 'vehicle_owners', 'stock_take_lines', 'stock_takes', 'customer_deposits', 'campaigns', 'notifications', 'tasks', 'feedback', 'loan_bookings', 'loan_vehicles', 'job_checklist_items', 'checklist_templates', 'canned_jobs', 'bookings'] as $t) {
            $this->forge->dropTable($t, true);
        }
        $this->forge->dropColumn('inspections', ['estimate', 'customer_decision', 'decided_at', 'followed_up_at']);
        $this->forge->dropColumn('job_cards', ['damage_marks', 'loan_vehicle_id', 'labour_cost', 'parts_cost', 'warranty_claim', 'canned_job_id']);
        $this->forge->dropColumn('line_items', 'warranty_months');
        $this->forge->dropColumn('products', ['warranty_months', 'labour_hours']);
        $this->forge->dropColumn('users', ['totp_secret', 'totp_enabled', 'commission_percent', 'monthly_target']);
        $this->forge->dropColumn('vehicles', ['service_reminder_sent_at', 'licence_reminder_sent_at']);
        $this->forge->dropColumn('customers', ['sms_consent', 'last_statement_sent_at']);
    }
}
