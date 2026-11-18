<?php

namespace App\Console\Commands;

use App\Domain\DomainException;
use App\Services\Install\Installer;
use Illuminate\Console\Command;

/** SSH installation path: uses DB settings from .env, runs migrations, seeds base data and creates the first admin. */
class InstallCommand extends Command
{
    protected $signature = 'amos:install {--admin-name=} {--admin-username=} {--admin-password=} {--fund-name=AMOS}';

    protected $description = 'Install AMOS using database settings from .env (migrate, base data, first admin, lock)';

    public function handle(Installer $installer): int
    {
        if (amos_installed()) {
            $this->error('AMOS is already installed (storage/app/installed.lock exists). Re-install is not allowed.');

            return self::FAILURE;
        }
        $name = $this->option('admin-name') ?: $this->ask('Admin full name');
        $username = $this->option('admin-username') ?: $this->ask('Admin username');
        $password = $this->option('admin-password') ?: $this->secret('Admin password (min 10 chars, upper/lower/digit)');
        if (strlen((string) $password) < 10 || ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password)) {
            $this->error('Password must be at least 10 characters with upper, lower case letters and digits.');

            return self::FAILURE;
        }
        $db = config('database.connections.mysql');
        try {
            $installer->install(
                ['host' => $db['host'], 'port' => $db['port'], 'database' => $db['database'], 'username' => $db['username'], 'password' => (string) $db['password']],
                ['name' => $name, 'username' => $username, 'password' => $password],
                ['url' => config('app.url'), 'fund_name' => $this->option('fund-name')],
                writeEnv: false,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('AMOS installed. Login with the admin account at '.config('app.url'));

        return self::SUCCESS;
    }
}
