<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-time installer for hosts without SSH/Terminal. Run it from a cPanel Cron Job:
 *   /opt/cpanel/ea-php82/root/usr/bin/php /home/USER/repositories/wheelturngms/backend/spark gms:install
 *
 * Safe to run more than once: it only does what is still missing.
 * 1. generates encryption.key and jwt.secret in .env if they are still placeholders
 * 2. checks the database connection
 * 3. runs all migrations
 * 4. seeds the chart of accounts, settings, main branch and admin user
 * 5. replaces the publicly-known default admin password with a random one
 * Results (and the admin password) are written to writable/logs/INSTALL-RESULT.txt.
 */
class Install extends BaseCommand
{
    protected $group       = 'Wheelsturn';
    protected $name        = 'gms:install';
    protected $description = 'Install / repair the database without Terminal access (run once via cPanel Cron).';

    private array $log = [];

    private function out(string $line): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $line;
        CLI::write($line);
    }

    public function run(array $params)
    {
        $resultFile = WRITEPATH . 'logs/INSTALL-RESULT.txt';
        try {
            if ($this->secrets()) {
                $this->out('STEP 1 of 2 DONE: security keys created. Leave the cron job running; the database installs on the next run (within a minute).');
                @file_put_contents($resultFile, implode("
", $this->log) . "
");
                return;
            }
            $db = db_connect();
            $db->initialize();
            $this->out('Database connection OK (' . $db->DBDriver . ', ' . $db->getDatabase() . ').');

            $migrations = service('migrations');
            $migrations->setNamespace(null);
            $migrations->latest();
            $this->out('Migrations complete.');

            (new \App\Database\Seeds\InitialSeeder(config('Database')))->run();
            $this->out('Chart of accounts, default settings, main branch and admin user are in place.');

            $admin = $db->table('users')->where('role', 'admin')->orderBy('id')->get()->getRowArray();
            if ($admin && password_verify('Admin@12345', $admin['password_hash'])) {
                $password = 'Wt-' . substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12) . '!';
                $db->table('users')->where('id', $admin['id'])->update(['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                $this->out('');
                $this->out('ADMIN LOGIN  email: ' . $admin['email']);
                $this->out('             password: ' . $password);
                $this->out('Sign in, change this password under My profile, then DELETE this file.');
            } else {
                $this->out('Admin password was already changed: left as is.');
            }
            $this->out('');
            $this->out('SUCCESS. Now delete the install cron job and add the gms:cron job (see DEPLOYMENT.md).');
        } catch (\Throwable $e) {
            $this->out('FAILED: ' . $e->getMessage());
            $this->out('Check the database name, user and password in backend/.env, then let the cron run again.');
        }
        @file_put_contents($resultFile, implode("\n", $this->log) . "\n");
        @chmod($resultFile, 0600);
    }

    /** Fill in encryption.key / jwt.secret in .env when they are still placeholders. */
    private function secrets(): bool
    {
        $envFile = ROOTPATH . '.env';
        if (!is_file($envFile)) {
            throw new \RuntimeException('backend/.env not found. Copy env.production.example to .env and fill in the database details first.');
        }
        $env = (string) file_get_contents($envFile);
        $changed = false;
        if (!preg_match('/^\s*encryption\.key\s*=\s*hex2bin:[0-9a-f]{64}/m', $env)) {
            $key = 'hex2bin:' . bin2hex(random_bytes(32));
            $env = preg_match('/^\s*encryption\.key\s*=.*$/m', $env) ? preg_replace('/^\s*encryption\.key\s*=.*$/m', 'encryption.key = ' . $key, $env) : rtrim($env) . "\nencryption.key = " . $key . "\n";
            $changed = true;
            $this->out('Generated a new encryption key.');
        }
        if (!preg_match("/^\s*jwt\.secret\s*=\s*'?[A-Za-z0-9]{40,}/m", $env) || str_contains($env, 'GENERATE_A_LONG_RANDOM_STRING')) {
            $secret = bin2hex(random_bytes(32));
            $env = preg_match('/^\s*jwt\.secret\s*=.*$/m', $env) ? preg_replace('/^\s*jwt\.secret\s*=.*$/m', "jwt.secret = '" . $secret . "'", $env) : rtrim($env) . "\njwt.secret = '" . $secret . "'\n";
            $changed = true;
            $this->out('Generated a new mobile-app (JWT) secret.');
        }
        if ($changed) {
            if (@file_put_contents($envFile, $env) === false) {
                throw new \RuntimeException('Could not write to backend/.env. Make sure the file is writable (permissions 640 or 644).');
            }
            // Keys are read at boot, so stop here; the next cron run does the database.
            return true;
        }
        return false;
    }
}
