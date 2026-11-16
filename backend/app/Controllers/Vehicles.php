<?php

namespace App\Controllers;

use App\Libraries\Activity;
use App\Libraries\Attachments as Files;

class Vehicles extends BaseController
{
    public const MAKES = ['Audi', 'BMW', 'Chery', 'Chevrolet', 'Citroën', 'Datsun', 'Fiat', 'Ford', 'GWM', 'Haval', 'Honda', 'Hyundai', 'Isuzu', 'Jaguar', 'Jeep', 'Kia', 'Land Rover', 'Lexus', 'Mahindra', 'Mazda', 'Mercedes-Benz', 'Mini', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Porsche', 'Renault', 'Suzuki', 'Tata', 'Toyota', 'Volkswagen', 'Volvo'];

    private function query()
    {
        $b = $this->db->table('vehicles v')
            ->select('v.*, c.first_name, c.last_name, c.company_name, c.type')
            ->select('(SELECT MAX(j.created_at) FROM job_cards j WHERE j.vehicle_id = v.id AND j.deleted_at IS NULL) last_visit')
            ->join('customers c', 'c.id = v.customer_id')
            ->where('v.deleted_at', null);
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('v.registration', $q)->orLike('v.vin', $q)->orLike('v.make', $q)->orLike('v.model', $q)
                ->orLike('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.company_name', $q)->groupEnd();
        }
        if ($this->request->getGet('due') === '1') {
            $b->where('v.next_service_date <=', date('Y-m-d', strtotime('+30 days')));
        }
        return $b->orderBy('v.id', 'DESC');
    }

    public function index()
    {
        [$rows, $pager] = $this->paginate($this->query());
        $dueSoon = $this->db->table('vehicles')->where('deleted_at', null)->where('next_service_date <=', date('Y-m-d', strtotime('+30 days')))->countAllResults();
        return view('vehicles/index', compact('rows', 'pager', 'dueSoon'));
    }

    public function export(string $format)
    {
        $rows = array_map(static fn ($r) => $r + ['owner' => customer_name($r)], $this->query()->get()->getResultArray());
        return $this->exportTable($format, 'Vehicles', [
            'registration' => ['label' => 'Registration'], 'make' => ['label' => 'Make'], 'model' => ['label' => 'Model'], 'year' => ['label' => 'Year'],
            'vin' => ['label' => 'VIN'], 'odometer' => ['label' => 'Odometer', 'type' => 'number'], 'owner' => ['label' => 'Owner'],
            'next_service_date' => ['label' => 'Next service'], 'licence_expiry' => ['label' => 'Licence expiry'],
        ], $rows);
    }

    public function lookup()
    {
        $b = $this->db->table('vehicles')->select('id, registration, make, model, year, odometer')->where('deleted_at', null);
        if ($cid = (int) $this->request->getGet('customer_id')) {
            $b->where('customer_id', $cid);
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()->like('registration', $q)->orLike('make', $q)->orLike('model', $q)->orLike('vin', $q)->groupEnd();
        }
        return $this->json(array_map(static fn ($v) => ['id' => $v['id'], 'text' => trim("{$v['registration']} · {$v['year']} {$v['make']} {$v['model']}"), 'odometer' => $v['odometer']], $b->limit(30)->get()->getResultArray()));
    }

    public function form(?int $id = null)
    {
        $v = $id ? $this->db->table('vehicles')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray() : ['customer_id' => $this->request->getGet('customer_id')];
        if ($id && !$v) {
            return $this->notFound('Vehicle');
        }
        $customer = !empty($v['customer_id']) ? $this->db->table('customers')->where('id', $v['customer_id'])->get()->getRowArray() : null;
        return view('vehicles/form', ['v' => $v, 'customer' => $customer, 'makes' => self::MAKES]);
    }

    public function save(?int $id = null)
    {
        if (!$this->validate([
            'customer_id'  => 'required|is_natural_no_zero',
            'registration' => 'required|max_length[20]',
            'vin'          => 'permit_empty|max_length[40]',
            'year'         => 'permit_empty|integer|greater_than[1900]|less_than[2100]',
            'odometer'     => 'permit_empty|integer',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = $this->only(['customer_id', 'vin', 'make', 'model', 'variant', 'year', 'colour', 'engine_number', 'engine_size', 'fuel_type', 'transmission', 'odometer', 'licence_expiry', 'next_service_date', 'next_service_km', 'notes']);
        $data['registration'] = strtoupper(preg_replace('/\s+/', ' ', (string) $this->input('registration')));
        $data['vin'] = $data['vin'] ? strtoupper($data['vin']) : null;
        $data['updated_at'] = $this->now();

        $dupe = $this->db->table('vehicles')->where(['registration' => $data['registration'], 'deleted_at' => null]);
        if ($id) {
            $dupe->where('id !=', $id);
        }
        if ($dupe->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'A vehicle with registration ' . $data['registration'] . ' already exists.');
        }
        if ($id) {
            $this->db->table('vehicles')->where('id', $id)->update($data);
            Activity::log('vehicle', $id, 'updated', 'Updated vehicle ' . $data['registration']);
        } else {
            $this->db->table('vehicles')->insert($data + ['created_at' => $this->now()]);
            $id = (int) $this->db->insertID();
            Activity::log('vehicle', $id, 'created', 'Added vehicle ' . $data['registration']);
        }
        if ($ret = $this->request->getPost('return_to')) {
            return redirect()->to(site_url($ret) . (str_contains($ret, '?') ? '&' : '?') . 'customer_id=' . $data['customer_id'] . '&vehicle_id=' . $id)->with('success', 'Vehicle saved.');
        }
        return redirect()->to(site_url('vehicles/' . $id))->with('success', 'Vehicle saved.');
    }

    public function show(int $id)
    {
        $v = $this->db->table('vehicles')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
        if (!$v) {
            return $this->notFound('Vehicle');
        }
        $customer = $this->db->table('customers')->where('id', $v['customer_id'])->get()->getRowArray();
        $jobs = $this->db->table('job_cards')->where(['vehicle_id' => $id, 'deleted_at' => null])->orderBy('id', 'DESC')->get()->getResultArray();
        $jobIds = array_column($jobs, 'id') ?: [0];
        $lines = $this->db->table('line_items')->select('document_id, item_type, description, quantity')
            ->where('document_type', 'job_card')->whereIn('document_id', $jobIds)->orderBy('sort_order')->get()->getResultArray();
        $workByJob = [];
        foreach ($lines as $l) {
            $workByJob[$l['document_id']][] = $l;
        }
        $spent = (float) $this->db->table('invoices')->selectSum('total')->where(['vehicle_id' => $id, 'deleted_at' => null])->whereNotIn('status', ['draft', 'void'])->get()->getRow()->total;
        $advisories = $this->db->table('inspections i')->select('i.*, j.number, j.created_at job_date')->join('job_cards j', 'j.id = i.job_card_id')
            ->where('j.vehicle_id', $id)->whereIn('i.status', ['advisory', 'urgent'])->orderBy('j.id', 'DESC')->limit(20)->get()->getResultArray();
        return view('vehicles/show', [
            'v' => $v, 'customer' => $customer, 'jobs' => $jobs, 'workByJob' => $workByJob, 'spent' => $spent, 'advisories' => $advisories,
            'files' => Files::for('vehicle', $id), 'activity' => Activity::for('vehicle', $id),
            'owners' => $this->db->table('vehicle_owners o')->select('o.*, c.first_name, c.last_name, c.company_name, c.type')->join('customers c', 'c.id = o.customer_id')->where('o.vehicle_id', $id)->orderBy('o.id', 'DESC')->get()->getResultArray(),
            'aiEnabled' => \App\Libraries\AiAssistant::enabled(),
        ]);
    }

    /**
     * Decode a VIN with the free NHTSA vPIC service (works for most global manufacturers;
     * some South African-market models return partial data). Only the VIN is sent.
     */
    public function decodeVin()
    {
        $vin = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->request->getGet('vin')));
        if (strlen($vin) !== 17 || preg_match('/[IOQ]/', $vin)) {
            return $this->json(['error' => 'A VIN has 17 characters and never contains I, O or Q.'], 422);
        }
        try {
            $res = service('curlrequest', ['timeout' => 12, 'http_errors' => false], null, false)
                ->get('https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/' . $vin . '?format=json');
            $r = json_decode($res->getBody(), true)['Results'][0] ?? null;
        } catch (\Throwable $e) {
            return $this->json(['error' => 'VIN service unreachable. Enter details manually.'], 503);
        }
        if (!$r || (!$r['Make'] && !$r['ModelYear'])) {
            return $this->json(['error' => 'No data found for this VIN. Enter details manually.'], 404);
        }
        $title = static fn ($s) => $s ? ucwords(strtolower(trim($s))) : null;
        $engine = $r['DisplacementL'] ? round((float) $r['DisplacementL'], 1) . 'L' : null;
        return $this->json([
            'make' => $title($r['Make']), 'model' => trim((string) $r['Model']) ?: null, 'year' => $r['ModelYear'] ?: null,
            'variant' => trim(implode(' ', array_filter([$r['Trim'], $r['Series']]))) ?: null, 'engine_size' => $engine,
            'fuel_type' => match (true) { str_contains((string) $r['FuelTypePrimary'], 'Diesel') => 'Diesel', str_contains((string) $r['FuelTypePrimary'], 'Electric') => 'Electric', str_contains((string) $r['FuelTypePrimary'], 'Gasoline') => 'Petrol', default => null },
            'transmission' => str_contains(strtolower((string) $r['TransmissionStyle']), 'manual') ? 'Manual' : ($r['TransmissionStyle'] ? 'Automatic' : null),
            'body' => $r['BodyClass'] ?: null, 'plant' => $title($r['PlantCountry']),
        ]);
    }

    /** Transfer ownership to another customer, keeping the history. */
    public function transfer(int $id)
    {
        $v = $this->db->table('vehicles')->where('id', $id)->get()->getRowArray() ?? $this->notFound('Vehicle');
        $to = (int) $this->request->getPost('customer_id');
        if (!$to || $to === (int) $v['customer_id']) {
            return redirect()->back()->with('error', 'Choose the new owner.');
        }
        $date = $this->input('date', date('Y-m-d'));
        $this->db->transStart();
        if (!$this->db->table('vehicle_owners')->where('vehicle_id', $id)->countAllResults()) {
            $this->db->table('vehicle_owners')->insert(['vehicle_id' => $id, 'customer_id' => $v['customer_id'], 'from_date' => substr((string) $v['created_at'], 0, 10), 'to_date' => $date, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        } else {
            $this->db->table('vehicle_owners')->where(['vehicle_id' => $id, 'to_date' => null])->update(['to_date' => $date]);
        }
        $this->db->table('vehicle_owners')->insert(['vehicle_id' => $id, 'customer_id' => $to, 'from_date' => $date, 'note' => $this->input('note'), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        $this->db->table('vehicles')->where('id', $id)->update(['customer_id' => $to, 'updated_at' => $this->now()]);
        $this->db->transComplete();
        Activity::log('vehicle', $id, 'transfer', 'Ownership transferred');
        return redirect()->back()->with('success', 'Ownership transferred. Service history stays with the vehicle.');
    }

    public function delete(int $id)
    {
        $this->db->table('vehicles')->where('id', $id)->update(['deleted_at' => $this->now()]);
        Activity::log('vehicle', $id, 'deleted', 'Archived vehicle');
        return redirect()->to(site_url('vehicles'))->with('success', 'Vehicle archived.');
    }
}
