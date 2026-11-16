<?php

namespace App\Database\Seeds;

use App\Libraries\Billing;
use App\Libraries\Ledger;
use App\Libraries\LineItems;
use App\Libraries\Numbering;
use App\Libraries\Settings;
use CodeIgniter\Database\Seeder;

/**
 * Realistic demo data (≈10 weeks of trading) for training and demos.
 *   php spark db:seed DemoSeeder
 * Do NOT run on the live database.
 */
class DemoSeeder extends Seeder
{
    private function at(int $daysAgo, int $hour = 9): string
    {
        return date('Y-m-d H:i:s', strtotime("-{$daysAgo} days {$hour}:" . str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT)));
    }

    public function run(): void
    {
        helper(['app', 'url', 'number', 'text']);
        mt_srand(2026);
        $db = $this->db;
        $now = date('Y-m-d H:i:s');
        Settings::set('company_vat_number', '4123456789', 'company');
        Settings::set('company_phone', '011 555 0199', 'company');
        Settings::set('company_address', "12 Workshop Avenue\nMidrand, Gauteng\n1685", 'company');
        Settings::set('bank_name', 'FNB', 'company');
        Settings::set('bank_account_number', '62812345678', 'company');
        Settings::set('bank_branch_code', '250655', 'company');

        // Team
        $team = [
            ['manager', 'Thabo', 'Mokoena', 'Workshop Manager', 0], ['advisor', 'Lerato', 'Dlamini', 'Service Advisor', 0],
            ['mechanic', 'Sipho', 'Nkosi', 'Senior Technician', 650], ['mechanic', 'Johan', 'van Wyk', 'Technician', 550],
            ['mechanic', 'Ayanda', 'Zulu', 'Diagnostic Specialist', 750], ['accountant', 'Priya', 'Naidoo', 'Bookkeeper', 0],
        ];
        $mechanics = [];
        foreach ($team as [$role, $f, $l, $title, $rate]) {
            $email = strtolower($f) . '@wheelsturn.co.za';
            if (!$db->table('users')->where('email', $email)->countAllResults()) {
                $db->table('users')->insert(['branch_id' => 1, 'role' => $role, 'first_name' => $f, 'last_name' => $l, 'email' => $email, 'job_title' => $title, 'hourly_rate' => $rate, 'cost_rate' => $rate ? 180 : 0, 'password_hash' => password_hash('Demo@12345', PASSWORD_DEFAULT), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($role === 'mechanic') {
                $mechanics[] = (int) $db->table('users')->where('email', $email)->get()->getRow()->id;
            }
        }

        // Suppliers
        $suppliers = [];
        foreach (['Midas Midrand', 'Goldwagen', 'AutoZone Distribution', 'Engen Lubricants', 'Tiger Wheel & Tyre'] as $s) {
            $db->table('suppliers')->insert(['name' => $s, 'phone' => '011 ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999), 'payment_terms_days' => 30, 'vat_number' => '4' . mt_rand(100000000, 999999999), 'created_at' => $now, 'updated_at' => $now]);
            $suppliers[] = (int) $db->insertID();
        }

        // Price list
        $catalogue = [
            ['LAB-STD', 'Labour – mechanical', 'labour', 'Labour', 0, 650, 0],
            ['LAB-DIAG', 'Computer diagnostics', 'service', 'Diagnostics', 0, 550, 0],
            ['SVC-MINOR', 'Minor service labour', 'service', 'Services', 0, 950, 0],
            ['SVC-MAJOR', 'Major service labour', 'service', 'Services', 0, 1850, 0],
            ['OIL-5W30', 'Engine oil 5W-30 (1L)', 'fluid', 'Oils & fluids', 95, 165, 120],
            ['OIL-FLT', 'Oil filter', 'part', 'Filters', 65, 145, 60],
            ['AIR-FLT', 'Air filter', 'part', 'Filters', 110, 245, 40],
            ['FUEL-FLT', 'Fuel filter (diesel)', 'part', 'Filters', 210, 420, 25],
            ['PAD-F', 'Brake pads – front set', 'part', 'Brakes', 420, 890, 30],
            ['DISC-F', 'Brake discs – front pair', 'part', 'Brakes', 980, 1950, 12],
            ['PLUG-IR', 'Spark plug (iridium)', 'part', 'Ignition', 95, 210, 48],
            ['BATT-652', 'Battery 652 (Willard)', 'part', 'Electrical', 1350, 2250, 8],
            ['WIPER', 'Wiper blade set', 'part', 'Accessories', 120, 260, 30],
            ['COOL-5L', 'Coolant 5L', 'fluid', 'Oils & fluids', 160, 320, 20],
            ['SHOCK-F', 'Shock absorber – front', 'part', 'Suspension', 750, 1450, 10],
            ['CLUTCH', 'Clutch kit', 'part', 'Drivetrain', 2600, 4850, 4],
            ['TYRE-195', 'Tyre 195/65R15', 'tyre', 'Tyres', 850, 1395, 16],
            ['ALIGN', 'Wheel alignment', 'service', 'Tyres', 0, 450, 0],
            ['AC-REGAS', 'Aircon regas', 'service', 'Air conditioning', 180, 950, 0],
            ['SUNDRY', 'Workshop consumables', 'sundry', 'Sundry', 0, 85, 0],
        ];
        $products = [];
        foreach ($catalogue as $i => [$sku, $name, $type, $cat, $cost, $sell, $qty]) {
            $db->table('products')->insert(['sku' => $sku, 'name' => $name, 'type' => $type, 'category' => $cat, 'unit' => $type === 'fluid' ? 'litre' : 'each', 'cost_price' => $cost, 'sell_price' => $sell, 'vat_rate' => 15, 'track_stock' => $qty > 0 ? 1 : 0, 'qty_on_hand' => $qty, 'reorder_level' => $qty > 0 ? max(2, (int) ($qty / 4)) : 0, 'supplier_id' => $suppliers[$i % count($suppliers)], 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $products[$sku] = $db->table('products')->where('sku', $sku)->get()->getRowArray();
        }
        // Opening stock journal so the balance sheet reflects inventory funded by owner's capital
        $stockValue = array_sum(array_map(static fn ($p) => (float) $p['cost_price'] * (float) $p['qty_on_hand'], $products));
        Ledger::post('opening', 0, date('Y-m-d', strtotime('-80 days')), 'Opening balances', [
            ['account' => '1000', 'debit' => 150000], ['account' => '1200', 'debit' => $stockValue], ['account' => '1500', 'debit' => 280000],
            ['account' => '3000', 'credit' => 430000 + $stockValue],
        ]);

        // Customers & vehicles
        $first = ['Nomsa', 'Pieter', 'Kagiso', 'Fatima', 'Michael', 'Zanele', 'Ruan', 'Busi', 'Ahmed', 'Karabo', 'Elize', 'Themba', 'Grace', 'Sizwe', 'Chantel', 'David', 'Lindiwe', 'Mpho', 'Riaan', 'Naledi'];
        $last = ['Khumalo', 'Botha', 'Molefe', 'Patel', 'Smith', 'Ndlovu', 'Pretorius', 'Mahlangu', 'Moosa', 'Sithole', 'Venter', 'Mabaso', 'Jacobs', 'Cele', 'Fourie'];
        $fleet = ['Bidvest Logistics', 'Afrifresh Deliveries', 'Midrand Plumbing CC', 'Sunrise Shuttles'];
        $cars = [['Toyota', 'Hilux', '2.8 GD-6 Raider'], ['Toyota', 'Corolla Cross', '1.8 Xi'], ['Volkswagen', 'Polo Vivo', '1.4 Trendline'], ['Volkswagen', 'Polo', '1.0 TSI Comfortline'], ['Ford', 'Ranger', '2.0 SiT XLT'], ['Suzuki', 'Swift', '1.2 GL'], ['Hyundai', 'Grand i10', '1.25 Fluid'], ['Nissan', 'NP200', '1.6 8V'], ['BMW', '320i', 'M Sport'], ['Isuzu', 'D-Max', '250 HO'], ['Kia', 'Picanto', '1.2 Style'], ['Mercedes-Benz', 'C200', 'AMG Line'], ['Renault', 'Kwid', '1.0 Dynamique'], ['Haval', 'Jolion', '1.5T Luxury']];
        $colours = ['White', 'Silver', 'Grey', 'Black', 'Red', 'Blue'];
        $customers = [];
        for ($i = 0; $i < 34; $i++) {
            $isBiz = $i < count($fleet);
            $fn = $first[$i % count($first)];
            $ln = $last[($i * 7) % count($last)];
            $daysAgo = mt_rand(60, 400);
            $db->table('customers')->insert([
                'branch_id' => 1, 'code' => Numbering::next('customer'), 'type' => $isBiz ? 'business' : 'individual', 'first_name' => $fn, 'last_name' => $ln,
                'company_name' => $isBiz ? $fleet[$i] : null, 'vat_number' => $isBiz ? '4' . mt_rand(100000000, 999999999) : null,
                'email' => strtolower($fn . '.' . $ln . $i) . '@example.co.za', 'phone' => '08' . mt_rand(1, 4) . ' ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999),
                'city' => ['Midrand', 'Centurion', 'Sandton', 'Fourways', 'Kempton Park'][$i % 5], 'province' => 'Gauteng',
                'payment_terms_days' => $isBiz ? 30 : 0, 'source' => ['Walk-in', 'Google', 'Referral', 'Facebook', 'Returning customer'][$i % 5],
                'popia_consent_at' => $now, 'is_active' => 1, 'created_at' => $i > 28 ? $this->at(mt_rand(1, 20)) : $this->at($daysAgo), 'updated_at' => $now,
            ]);
            $cid = (int) $db->insertID();
            $nVeh = $isBiz ? 3 : (mt_rand(0, 4) === 0 ? 2 : 1);
            for ($v = 0; $v < $nVeh; $v++) {
                [$make, $model, $variant] = $cars[mt_rand(0, count($cars) - 1)];
                $db->table('vehicles')->insert([
                    'customer_id' => $cid, 'registration' => chr(65 + mt_rand(0, 25)) . chr(65 + mt_rand(0, 25)) . ' ' . mt_rand(10, 99) . ' ' . chr(65 + mt_rand(0, 25)) . chr(65 + mt_rand(0, 25)) . ' GP',
                    'vin' => strtoupper(substr(md5((string) mt_rand()), 0, 17)), 'make' => $make, 'model' => $model, 'variant' => $variant, 'year' => mt_rand(2012, 2025),
                    'colour' => $colours[mt_rand(0, 5)], 'fuel_type' => in_array($make, ['Isuzu'], true) || $model === 'Hilux' ? 'Diesel' : 'Petrol', 'transmission' => mt_rand(0, 2) ? 'Manual' : 'Automatic',
                    'odometer' => mt_rand(25000, 210000), 'next_service_date' => date('Y-m-d', strtotime('+' . mt_rand(-10, 120) . ' days')), 'created_at' => $now, 'updated_at' => $now,
                ]);
                $customers[] = ['customer_id' => $cid, 'vehicle_id' => (int) $db->insertID(), 'biz' => $isBiz];
            }
        }

        // Job cards over ~10 weeks
        $complaints = ['Due for major service', 'Minor service and check brakes', 'Grinding noise when braking', 'Engine warning light on', 'Aircon not cold', 'Clutch slipping', 'Car pulls to the left', 'Battery keeps going flat', 'Knocking noise over bumps', 'Annual service + roadworthy check'];
        $templates = [
            [['SVC-MINOR', 1], ['OIL-5W30', 4.5], ['OIL-FLT', 1], ['AIR-FLT', 1], ['SUNDRY', 1]],
            [['SVC-MAJOR', 1], ['OIL-5W30', 5], ['OIL-FLT', 1], ['AIR-FLT', 1], ['PLUG-IR', 4], ['COOL-5L', 1], ['SUNDRY', 1]],
            [['LAB-STD', 1.5], ['PAD-F', 1], ['DISC-F', 1], ['SUNDRY', 1]],
            [['LAB-DIAG', 1], ['LAB-STD', 1]],
            [['AC-REGAS', 1], ['LAB-STD', 0.5]],
            [['LAB-STD', 5], ['CLUTCH', 1], ['SUNDRY', 1]],
            [['ALIGN', 1], ['TYRE-195', 2]],
            [['LAB-DIAG', 1], ['BATT-652', 1]],
            [['LAB-STD', 2.5], ['SHOCK-F', 2]],
            [['SVC-MINOR', 1], ['OIL-5W30', 4], ['OIL-FLT', 1], ['WIPER', 1]],
        ];
        $methods = ['eft', 'eft', 'card', 'card', 'card', 'cash', 'payfast', 'ozow'];
        $jobsTotal = 92;
        for ($j = 0; $j < $jobsTotal; $j++) {
            $daysAgo = (int) round(70 - $j * (70 / $jobsTotal)) + mt_rand(0, 1);
            $daysAgo = max(0, $daysAgo);
            $cv = $customers[mt_rand(0, count($customers) - 1)];
            $t = mt_rand(0, count($templates) - 1);
            $created = $this->at($daysAgo, mt_rand(7, 10));
            $recent = $daysAgo <= 2;
            $status = $recent ? ['checked_in', 'in_progress', 'awaiting_parts', 'awaiting_approval', 'quality_check', 'completed', 'booked'][mt_rand(0, 6)] : (mt_rand(0, 15) === 0 ? 'cancelled' : 'collected');
            $completed = in_array($status, ['completed', 'collected'], true) ? date('Y-m-d H:i:s', strtotime($created . ' +' . mt_rand(3, 30) . ' hours')) : null;
            $mechId = $mechanics[mt_rand(0, count($mechanics) - 1)];
            $db->table('job_cards')->insert([
                'number' => Numbering::next('job_card'), 'branch_id' => 1, 'customer_id' => $cv['customer_id'], 'vehicle_id' => $cv['vehicle_id'], 'status' => $status,
                'priority' => mt_rand(0, 8) === 0 ? 'urgent' : 'normal', 'job_type' => ['Service', 'Service', 'Brakes', 'Diagnostics', 'Air conditioning', 'Clutch & gearbox', 'Tyres & alignment', 'Electrical', 'Suspension', 'Service'][$t],
                'advisor_id' => 3, 'bay' => 'Bay ' . mt_rand(1, 4), 'odometer_in' => mt_rand(30000, 200000), 'fuel_level' => ['1/4', '1/2', '3/4'][mt_rand(0, 2)],
                'customer_complaint' => $complaints[$t], 'work_performed' => $completed ? 'Work completed as per job card. Vehicle road tested.' : null,
                'booked_for' => $created, 'promised_at' => date('Y-m-d H:i:s', strtotime($created . ' +8 hours')), 'checked_in_at' => $status === 'booked' ? null : $created,
                'started_at' => in_array($status, ['booked', 'checked_in'], true) ? null : $created, 'completed_at' => $completed, 'collected_at' => $status === 'collected' ? $completed : null,
                'public_token' => Billing::token(), 'created_at' => $created, 'updated_at' => $created,
            ]);
            $jobId = (int) $db->insertID();
            $db->table('job_card_mechanics')->insert(['job_card_id' => $jobId, 'user_id' => $mechId, 'created_at' => $created]);
            $lines = [];
            foreach ($templates[$t] as [$sku, $qty]) {
                $p = $products[$sku];
                $lines[] = ['item_type' => $p['type'], 'product_id' => $p['id'], 'description' => $p['name'], 'quantity' => $qty, 'unit_price' => $p['type'] === 'labour' ? 650 : $p['sell_price'], 'unit_cost' => $p['cost_price'], 'discount_percent' => $cv['biz'] ? 5 : 0, 'vat_rate' => 15, 'mechanic_id' => in_array($p['type'], ['labour', 'service'], true) ? $mechId : null];
            }
            LineItems::sync('job_card', $jobId, $lines);
            // inspection sample
            foreach ([['Tyres', 'Front left tyre'], ['Under vehicle', 'Brake pads & discs'], ['Under bonnet', 'Battery & terminals'], ['Exterior', 'Wiper blades']] as $k => [$sec, $item]) {
                $db->table('inspections')->insert(['job_card_id' => $jobId, 'section' => $sec, 'item' => $item, 'status' => ['ok', 'ok', 'advisory', 'ok', 'urgent'][mt_rand(0, 4)], 'note' => null, 'sort_order' => $k, 'created_at' => $created, 'updated_at' => $created]);
            }
            if ($completed || $status === 'in_progress') {
                $hours = array_sum(array_map(static fn ($l) => in_array($l['item_type'], ['labour', 'service'], true) ? (float) $l['quantity'] : 0, $lines)) * (mt_rand(80, 125) / 100);
                $start = strtotime($created . ' +30 minutes');
                $db->table('time_logs')->insert(['job_card_id' => $jobId, 'user_id' => $mechId, 'started_at' => date('Y-m-d H:i:s', $start), 'ended_at' => $completed ? date('Y-m-d H:i:s', $start + (int) ($hours * 3600)) : null, 'minutes' => $completed ? (int) round($hours * 60) : 0, 'created_at' => $created, 'updated_at' => $created]);
            }
            if ($status === 'collected') {
                $invId = Billing::jobCardToInvoice($jobId);
                $issue = substr($completed, 0, 10);
                $terms = $cv['biz'] ? 30 : 0;
                $db->table('invoices')->where('id', $invId)->update(['issue_date' => $issue, 'due_date' => date('Y-m-d', strtotime($issue . " +{$terms} days")), 'created_at' => $completed]);
                $db->table('job_cards')->where('id', $jobId)->update(['status' => 'collected']);
                Ledger::postInvoice($invId);
                $inv = $db->table('invoices')->where('id', $invId)->get()->getRowArray();
                $payRoll = mt_rand(0, 9);
                if ($payRoll < 8 || $daysAgo > 40) {
                    $amount = $payRoll === 7 ? round((float) $inv['total'] / 2, 2) : (float) $inv['total'];
                    $paidAt = date('Y-m-d H:i:s', strtotime($completed . ' +' . ($cv['biz'] ? mt_rand(5, 28) : 0) . ' days +' . mt_rand(0, 3) . ' hours'));
                    if (strtotime($paidAt) > time()) {
                        $paidAt = date('Y-m-d H:i:s');
                    }
                    Billing::recordPayment($invId, $amount, $methods[mt_rand(0, count($methods) - 1)], ['paid_at' => $paidAt, 'reference' => 'DEMO' . $invId, 'notify' => false]);
                }
            }
        }

        // A few quotes
        for ($q = 0; $q < 12; $q++) {
            $cv = $customers[mt_rand(0, count($customers) - 1)];
            $issued = date('Y-m-d', strtotime('-' . mt_rand(0, 40) . ' days'));
            $db->table('quotes')->insert(['number' => Numbering::next('quote'), 'branch_id' => 1, 'customer_id' => $cv['customer_id'], 'vehicle_id' => $cv['vehicle_id'], 'status' => ['sent', 'accepted', 'declined', 'sent', 'converted', 'draft'][mt_rand(0, 5)], 'issue_date' => $issued, 'expiry_date' => date('Y-m-d', strtotime($issued . ' +14 days')), 'terms' => Settings::get('quote_terms'), 'public_token' => Billing::token(), 'created_at' => $issued . ' 10:00:00', 'updated_at' => $now]);
            $qid = (int) $db->insertID();
            $tpl = $templates[mt_rand(0, count($templates) - 1)];
            LineItems::sync('quote', $qid, array_map(static fn ($x) => ['item_type' => $products[$x[0]]['type'], 'product_id' => $products[$x[0]]['id'], 'description' => $products[$x[0]]['name'], 'quantity' => $x[1], 'unit_price' => $products[$x[0]]['sell_price'], 'unit_cost' => $products[$x[0]]['cost_price'], 'vat_rate' => 15], $tpl));
        }

        // Weekly overheads & ad-hoc expenses
        $acc = static fn ($code) => (int) db_connect()->table('accounts')->where('code', $code)->get()->getRow()->id;
        $bank = $acc('1000');
        for ($w = 10; $w >= 0; $w--) {
            $d = date('Y-m-d', strtotime("-{$w} weeks monday"));
            foreach ([['6060', 'Engen Midrand', 'Fuel – courier bakkie', mt_rand(600, 1400)], ['6090', 'Goldwagen', 'Workshop consumables, rags & cleaners', mt_rand(400, 1200)]] as [$code, $payee, $desc, $amt]) {
                $this->expense($acc($code), $bank, $d, $payee, $desc, $amt);
            }
            if ((int) date('j', strtotime($d)) <= 7) {
                $this->expense($acc('6010'), $bank, date('Y-m-01', strtotime($d)), 'Midrand Industrial Park', 'Workshop rent', 28750);
                $this->expense($acc('6020'), $bank, date('Y-m-05', strtotime($d)), 'City Power', 'Electricity & water', mt_rand(5200, 7400));
                $this->expense($acc('6030'), $bank, date('Y-m-03', strtotime($d)), 'Vodacom Business', 'Fibre & cellphones', 1899);
                $this->expense($acc('6000'), $bank, date('Y-m-25', strtotime($d . ' -1 month')), 'Payroll', 'Salaries & wages', 96500, false);
                $this->expense($acc('6170'), $bank, date('Y-m-02', strtotime($d)), 'Microsoft', 'Software subscriptions', 1250);
            }
        }
        // Phase 2: purchasing, payables, credit note, manual journal
        foreach ([[18, ['PAD-F' => 10, 'DISC-F' => 4]], [9, ['OIL-5W30' => 60, 'OIL-FLT' => 24, 'AIR-FLT' => 12]], [3, ['BATT-652' => 4, 'TYRE-195' => 8]]] as $k => [$daysAgo, $order]) {
            $date = date('Y-m-d', strtotime("-{$daysAgo} days"));
            $db->table('purchase_orders')->insert(['number' => Numbering::next('purchase_order'), 'branch_id' => 1, 'supplier_id' => $suppliers[$k], 'status' => 'sent', 'order_date' => $date, 'expected_date' => $date, 'created_at' => $date . ' 08:00:00', 'updated_at' => $now]);
            $poId = (int) $db->insertID();
            LineItems::sync('purchase_order', $poId, array_map(static fn ($sku, $qty) => ['item_type' => $products[$sku]['type'], 'product_id' => $products[$sku]['id'], 'description' => $products[$sku]['name'], 'quantity' => $qty, 'unit_price' => $products[$sku]['cost_price'], 'vat_rate' => 15], array_keys($order), $order));
            if ($k < 2) {
                $billId = \App\Libraries\Purchasing::billFromPo($poId, 'SUP-INV-' . mt_rand(10000, 99999), $date);
                if ($k === 0) {
                    $bill = $db->table('supplier_bills')->where('id', $billId)->get()->getRowArray();
                    \App\Libraries\Purchasing::pay($billId, (float) $bill['total'], $bank, 'eft', 'GW' . $billId, date('Y-m-d H:i:s', strtotime($date . ' +5 days')));
                }
            }
        }
        // Sublet bill straight to an expense account
        $db->table('supplier_bills')->insert(['number' => Numbering::next('bill'), 'supplier_invoice' => 'MID-7781', 'branch_id' => 1, 'supplier_id' => $suppliers[0], 'status' => 'unpaid', 'bill_date' => date('Y-m-d', strtotime('-40 days')), 'due_date' => date('Y-m-d', strtotime('-10 days')), 'created_at' => $now, 'updated_at' => $now]);
        $subId = (int) $db->insertID();
        LineItems::sync('bill', $subId, [['item_type' => 'sublet', 'description' => 'Exhaust fabrication (sublet)', 'quantity' => 1, 'unit_price' => 2400, 'vat_rate' => 15, 'account_id' => $acc('5100')]]);
        \App\Libraries\Purchasing::refreshBill($subId);
        Ledger::postBill($subId);

        // A credit note on a recent paid invoice (goodwill discount)
        $inv = $db->table('invoices')->where('status', 'unpaid')->orderBy('id', 'DESC')->get()->getRowArray();
        if ($inv) {
            $db->table('credit_notes')->insert(['number' => Numbering::next('credit_note'), 'branch_id' => 1, 'invoice_id' => $inv['id'], 'customer_id' => $inv['customer_id'], 'issue_date' => date('Y-m-d'), 'reason' => 'Goodwill discount on labour', 'restock' => 0, 'status' => 'issued', 'created_at' => $now, 'updated_at' => $now]);
            $cnId = (int) $db->insertID();
            LineItems::sync('credit_note', $cnId, [['item_type' => 'labour', 'description' => 'Goodwill: labour discount', 'quantity' => 1, 'unit_price' => 200, 'vat_rate' => 15]]);
            Ledger::postCreditNote($cnId);
            Billing::refreshInvoiceStatus((int) $inv['id']);
        }
        // Monthly depreciation journal
        Ledger::post('manual', null, date('Y-m-t', strtotime('last day of last month')), 'Depreciation – workshop equipment (monthly)', [
            ['account' => '6120', 'debit' => 4666.67], ['account' => '1510', 'credit' => 4666.67],
        ], 'DEP-' . date('Ym', strtotime('last month')));

        echo "Demo data created. Staff logins use password Demo@12345 (e.g. thabo@wheelsturn.co.za).\n";
    }

    private function expense(int $accountId, int $bank, string $date, string $payee, string $desc, float $total, bool $vat = true): void
    {
        if (strtotime($date) > time()) {
            return;
        }
        $vatAmt = $vat ? round($total * 15 / 115, 2) : 0;
        $this->db->table('expenses')->insert(['number' => Numbering::next('expense'), 'branch_id' => 1, 'expense_date' => $date, 'payee' => $payee, 'account_id' => $accountId, 'paid_from_id' => $bank, 'description' => $desc, 'amount_excl' => $total - $vatAmt, 'vat_amount' => $vatAmt, 'total' => $total, 'payment_method' => 'eft', 'is_vat_claimable' => $vat ? 1 : 0, 'created_at' => $date . ' 09:00:00', 'updated_at' => $date . ' 09:00:00']);
        Ledger::postExpense((int) $this->db->insertID());
    }
}
