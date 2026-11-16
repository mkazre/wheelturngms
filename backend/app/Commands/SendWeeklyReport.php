<?php

namespace App\Commands;

use App\Libraries\WeeklyReport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SendWeeklyReport extends BaseCommand
{
    protected $group       = 'Wheelsturn';
    protected $name        = 'reports:weekly';
    protected $description = 'Email the weekly performance report now.';
    protected $usage       = 'reports:weekly [--to email@domain.com]';

    public function run(array $params)
    {
        helper(['url', 'app', 'number', 'text']);
        $to = CLI::getOption('to');
        $ok = WeeklyReport::send(true, is_string($to) ? $to : null);
        CLI::write($ok ? 'Weekly report sent.' : 'Weekly report could not be sent. Check recipients/SMTP settings and writable/logs.', $ok ? 'green' : 'red');
    }
}
