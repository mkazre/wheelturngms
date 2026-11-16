<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Role → module permissions. '*' grants everything.
 */
class Permissions extends BaseConfig
{
    public array $labels = [
        'admin'      => 'Administrator',
        'manager'    => 'Workshop Manager',
        'advisor'    => 'Service Advisor',
        'mechanic'   => 'Technician / Mechanic',
        'accountant' => 'Accountant',
        'customer'   => 'Customer',
    ];

    public array $roles = [
        'admin'      => ['*'],
        'manager'    => ['dashboard', 'workshop', 'job_cards', 'quotes', 'invoices', 'payments', 'customers', 'vehicles', 'inventory', 'suppliers', 'purchases', 'expenses', 'accounting', 'reports', 'users', 'settings'],
        'advisor'    => ['dashboard', 'workshop', 'job_cards', 'quotes', 'invoices', 'payments', 'customers', 'vehicles', 'inventory', 'purchases'],
        'mechanic'   => ['workshop', 'job_cards', 'vehicles'],
        'accountant' => ['dashboard', 'invoices', 'payments', 'customers', 'suppliers', 'purchases', 'expenses', 'accounting', 'reports'],
        'customer'   => ['portal'],
    ];
}
