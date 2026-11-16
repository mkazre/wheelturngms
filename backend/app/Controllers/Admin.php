<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Backup;
use App\Libraries\BankStatements;
use App\Libraries\Numbering;

/** Administration tools: audit log, backups, CSV data import, branch switching, command palette data. */
class Admin extends BaseController
{
    // ------------------------------------------------------------ audit log

    public function audit()
    {
        [$from, $to] = $this->range('30d');
        $b = $this->db->table('activity_logs a')->select('a.*, u.first_name, u.last_name, u.role')->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.created_at >=', $from . ' 00:00:00')->where('a.created_at <=', $to . ' 23:59:59');
        if ($uid = (int) $this->request->getGet('user')) {
            $b->where('a.user_id', $uid);
        }
        if ($t = $this->request->getGet('type')) {
            $b->where('a.entity_type', $t);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->like('a.description', $q);
        }
        [$rows, $pager] = $this->paginate($b->orderBy('a.id', 'DESC'), 50);
        return view('admin/audit', [
            'rows' => $rows, 'pager' => $pager, 'from' => $from, 'to' => $to,
            'users' => $this->db->table('users')->select('id, first_name, last_name')->where('role !=', 'customer')->orderBy('first_name')->get()->getResultArray(),
            'types' => array_column($this->db->table('activity_logs')->distinct()->select('entity_type')->where('entity_type IS NOT NULL')->get()->getResultArray(), 'entity_type'),
        ]);
    }

    // ------------------------------------------------------------ backups

    public function backups()
    {
        return view('admin/backups', ['files' => Backup::list()]);
    }

    public function backupNow()
    {
        $file = Backup::run(true);
        Activity::log('system', null, 'backup', 'Manual database backup created');
        return redirect()->back()->with('success', 'Backup created: ' . basename((string) $file));
    }

    public function backupDownload(string $name)
    {
        if (session('role') !== 'admin' || !preg_match('/^wheelsturn-\d{8}-\d{6}\.sql\.gz$/', $name) || !is_file(Backup::dir() . $name)) {
            return $this->notFound('Backup');
        }
        Activity::log('system', null, 'backup_download', 'Downloaded backup ' . $name);
        return $this->response->download(Backup::dir() . $name, null);
    }

    // ------------------------------------------------------------ data import

    public const IMPORT_FIELDS = [
        'customers' => ['first_name' => 'First name', 'last_name' => 'Last name', 'company_name' => 'Company', 'email' => 'Email', 'phone' => 'Mobile', 'vat_number' => 'VAT no.', 'address_line1' => 'Address', 'city' => 'City', 'postal_code' => 'Postal code', 'notes' => 'Notes'],
        'vehicles'  => ['registration' => 'Registration *', 'customer_email' => 'Owner email', 'customer_phone' => 'Owner phone', 'make' => 'Make', 'model' => 'Model', 'year' => 'Year', 'vin' => 'VIN', 'colour' => 'Colour', 'odometer' => 'Odometer'],
        'products'  => ['name' => 'Name *', 'sku' => 'SKU', 'barcode' => 'Barcode', 'category' => 'Category', 'brand' => 'Brand', 'cost_price' => 'Cost price', 'sell_price' => 'Sell price', 'qty_on_hand' => 'Qty on hand', 'reorder_level' => 'Reorder level'],
    ];

    public function import()
    {
        return view('admin/import', ['fields' => self::IMPORT_FIELDS]);
    }

    public function importUpload()
    {
        $type = (string) $this->request->getPost('type');
        $file = $this->request->getFile('file');
        if (!isset(self::IMPORT_FIELDS[$type]) || !$file || !$file->isValid() || !in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return redirect()->back()->with('error', 'Choose what to import and upload a CSV file (in Excel: File → Save As → CSV).');
        }
        $name = 'imp_' . bin2hex(random_bytes(8)) . '.csv';
        $file->move(WRITEPATH . 'uploads/statements', $name);
        $rows = BankStatements::readCsv(WRITEPATH . 'uploads/statements/' . $name, 30);
        $header = array_map('strtolower', $rows[0] ?? []);
        $guess = [];
        foreach (self::IMPORT_FIELDS[$type] as $key => $label) {
            $needle = strtolower(str_replace(['_', ' *'], [' ', ''], $label));
            foreach ($header as $i => $h) {
                if ($h !== '' && (str_contains($h, $needle) || str_contains($needle, $h) || str_replace(' ', '_', $h) === $key)) {
                    $guess[$key] = $i;
                    break;
                }
            }
        }
        return view('admin/import_map', ['type' => $type, 'file' => $name, 'rows' => $rows, 'fields' => self::IMPORT_FIELDS[$type], 'guess' => $guess]);
    }

    public function importRun()
    {
        $type = (string) $this->request->getPost('type');
        $file = basename((string) $this->request->getPost('file'));
        $path = WRITEPATH . 'uploads/statements/' . $file;
        if (!isset(self::IMPORT_FIELDS[$type]) || !preg_match('/^imp_[a-f0-9]{16}\.csv$/', $file) || !is_file($path)) {
            return redirect()->to(site_url('admin/import'))->with('error', 'Upload expired. Please upload again.');
        }
        $map = array_filter((array) $this->request->getPost('map'), static fn ($v) => $v !== '');
        $rows = array_slice(BankStatements::readCsv($path), (int) $this->request->getPost('skip'));
        @unlink($path);
        $added = $skipped = 0;
        $now = $this->now();
        $this->db->transStart();
        foreach ($rows as $r) {
            $v = [];
            foreach ($map as $field => $col) {
                $v[$field] = trim((string) ($r[(int) $col] ?? ''));
            }
            if ($type === 'customers') {
                if (($v['first_name'] ?? '') === '' && ($v['company_name'] ?? '') === '') {
                    $skipped++;
                    continue;
                }
                if (!empty($v['email']) && $this->db->table('customers')->where('email', strtolower($v['email']))->countAllResults()) {
                    $skipped++;
                    continue;
                }
                $this->db->table('customers')->insert(array_intersect_key($v, self::IMPORT_FIELDS['customers']) + [
                    'code' => Numbering::next('customer'), 'branch_id' => session('branch_id'), 'type' => !empty($v['company_name']) ? 'business' : 'individual',
                    'email' => !empty($v['email']) ? strtolower($v['email']) : null, 'source' => 'Import', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } elseif ($type === 'vehicles') {
                $reg = strtoupper($v['registration'] ?? '');
                if ($reg === '' || $this->db->table('vehicles')->where(['registration' => $reg, 'deleted_at' => null])->countAllResults()) {
                    $skipped++;
                    continue;
                }
                $owner = null;
                if (!empty($v['customer_email'])) {
                    $owner = $this->db->table('customers')->select('id')->where('email', strtolower($v['customer_email']))->get()->getRow()->id ?? null;
                }
                if (!$owner && !empty($v['customer_phone'])) {
                    $owner = $this->db->table('customers')->select('id')->where('phone', $v['customer_phone'])->get()->getRow()->id ?? null;
                }
                if (!$owner) {
                    $skipped++;
                    continue;
                }
                $this->db->table('vehicles')->insert(['customer_id' => $owner, 'registration' => $reg, 'make' => $v['make'] ?? null, 'model' => $v['model'] ?? null, 'year' => (int) ($v['year'] ?? 0) ?: null,
                    'vin' => !empty($v['vin']) ? strtoupper($v['vin']) : null, 'colour' => $v['colour'] ?? null, 'odometer' => (int) preg_replace('/\D/', '', $v['odometer'] ?? '') ?: null, 'created_at' => $now, 'updated_at' => $now]);
            } else {
                if (($v['name'] ?? '') === '' || (!empty($v['sku']) && $this->db->table('products')->where('sku', $v['sku'])->countAllResults())) {
                    $skipped++;
                    continue;
                }
                $num = static fn ($x) => (float) str_replace([' ', ','], ['', '.'], (string) $x);
                $qty = $num($v['qty_on_hand'] ?? 0);
                $this->db->table('products')->insert(['name' => $v['name'], 'sku' => ($v['sku'] ?? '') ?: null, 'barcode' => $v['barcode'] ?? null, 'category' => $v['category'] ?? null, 'brand' => $v['brand'] ?? null,
                    'type' => 'part', 'unit' => 'each', 'cost_price' => $num($v['cost_price'] ?? 0), 'sell_price' => $num($v['sell_price'] ?? 0), 'vat_rate' => setting('vat_rate', 15),
                    'track_stock' => 1, 'qty_on_hand' => $qty, 'reorder_level' => $num($v['reorder_level'] ?? 0), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            $added++;
        }
        $this->db->transComplete();
        Activity::log('system', null, 'import', "Imported {$added} {$type} ({$skipped} skipped)");
        $note = $type === 'products' ? ' Post an opening-stock journal (Dr Inventory, Cr Capital) for the imported stock value so the balance sheet matches.' : '';
        return redirect()->to(site_url('admin/import'))->with('success', "Imported {$added} {$type}. {$skipped} rows skipped (blank, duplicate or no matching owner).{$note}");
    }

    // ------------------------------------------------------------ branch switcher & palette

    public function switchBranch()
    {
        $id = (int) $this->request->getPost('branch_id');
        if ($this->db->table('branches')->where(['id' => $id, 'is_active' => 1])->countAllResults()) {
            session()->set('branch_id', $id);
        }
        return redirect()->back();
    }

    /** JSON for the Ctrl+K command palette: quick record search. */
    public function palette()
    {
        $q = trim((string) $this->request->getGet('q'));
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }
        $out = [];
        if (can('customers')) {
            foreach ($this->db->table('customers')->select('id, type, first_name, last_name, company_name, phone')->where('deleted_at', null)->groupStart()->like('first_name', $q)->orLike('last_name', $q)->orLike('company_name', $q)->orLike('phone', $q)->groupEnd()->limit(5)->get()->getResultArray() as $c) {
                $out[] = ['group' => 'Customers', 'label' => customer_name($c), 'hint' => $c['phone'], 'url' => site_url('customers/' . $c['id'])];
            }
        }
        if (can('vehicles')) {
            foreach ($this->db->table('vehicles')->select('id, registration, make, model')->where('deleted_at', null)->groupStart()->like('registration', $q)->orLike('vin', $q)->groupEnd()->limit(5)->get()->getResultArray() as $v) {
                $out[] = ['group' => 'Vehicles', 'label' => $v['registration'], 'hint' => trim($v['make'] . ' ' . $v['model']), 'url' => site_url('vehicles/' . $v['id'])];
            }
        }
        if (can('job_cards')) {
            foreach ($this->db->table('job_cards')->select('id, number, status')->where('deleted_at', null)->like('number', $q)->limit(5)->get()->getResultArray() as $j) {
                $out[] = ['group' => 'Job cards', 'label' => $j['number'], 'hint' => job_statuses()[$j['status']]['label'] ?? '', 'url' => site_url('job-cards/' . $j['id'])];
            }
        }
        if (can('invoices')) {
            foreach ($this->db->table('invoices')->select('id, number, total')->where('deleted_at', null)->like('number', $q)->limit(5)->get()->getResultArray() as $i) {
                $out[] = ['group' => 'Invoices', 'label' => $i['number'], 'hint' => money($i['total']), 'url' => site_url('invoices/' . $i['id'])];
            }
        }
        return $this->json($out);
    }
}
