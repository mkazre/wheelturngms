<?php

namespace App\Controllers;

use App\Libraries\Exporter;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    protected BaseConnection $db;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['url', 'form', 'text', 'number', 'app'];
        parent::initController($request, $response, $logger);
        $this->db = db_connect();
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** POST field with empty string normalised to null. */
    protected function input(string $key, $default = null)
    {
        $v = $this->request->getPost($key);
        if (is_string($v)) {
            $v = trim($v);
        }
        return ($v === '' || $v === null) ? $default : $v;
    }

    /** Pick a set of POST fields, normalising empties to null. */
    protected function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k);
        }
        return $out;
    }

    /**
     * Simple offset pagination for query builders. Returns [rows, pager meta].
     */
    protected function paginate($builder, int $perPage = 25): array
    {
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $total = (clone $builder)->countAllResults(false);
        $rows  = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return [$rows, ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) max(1, ceil($total / $perPage))]];
    }

    /** Date range from ?from=&to= with a sensible default (this month). */
    protected function range(string $default = 'month'): array
    {
        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        if (!$valid($from) || !$valid($to)) {
            [$from, $to] = match ($default) {
                'year'  => [date('Y-01-01'), date('Y-m-d')],
                '30d'   => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
                default => [date('Y-m-01'), date('Y-m-d')],
            };
        }
        return [$from, $to];
    }

    protected function exportTable(string $format, string $title, array $columns, array $rows, array $meta = [], array $totals = [])
    {
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            return redirect()->back()->with('error', 'Unknown export format.');
        }
        return Exporter::export($format, $title, $columns, $rows, $meta, $totals);
    }

    protected function json($data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    protected function notFound(string $what = 'Record')
    {
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound($what . ' not found.');
    }
}
