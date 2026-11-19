<?php

namespace App\Services\Install;

use App\Domain\DomainException;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * Safe installer used by both the web wizard (no-SSH hosts) and `php artisan amos:install`.
 * It never drops or overwrites an existing AMOS database.
 */
class Installer
{
    public const MIN_PHP = '8.3.0';
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'bcmath|gmp|none'];

    public static function requirements(): array
    {
        $checks = [
            ['PHP '.self::MIN_PHP.'+ (فعلی: '.PHP_VERSION.')', version_compare(PHP_VERSION, self::MIN_PHP, '>=')],
        ];
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'dom', 'filter', 'session'] as $ext) {
            $checks[] = ['افزونه '.$ext, extension_loaded($ext)];
        }
        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $checks[] = ['قابل نوشتن بودن '.$dir, is_writable(base_path($dir))];
        }
        $checks[] = ['قابل نوشتن بودن فایل .env', is_writable(app()->environmentFilePath()) || (! file_exists(app()->environmentFilePath()) && is_writable(dirname(app()->environmentFilePath())))];

        return $checks;
    }

    public static function testConnection(array $db): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']);
        try {
            return new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (Throwable $e) {
            throw new DomainException('اتصال به دیتابیس برقرار نشد. نام دیتابیس، نام کاربری، رمز و دسترسی کاربر را بررسی کنید.');
        }
    }

    /**
     * @param  array{host:string, port:int|string, database:string, username:string, password:string}  $db
     * @param  array{name:string, username:string, password:string}  $admin
     */
    public function install(array $db, array $admin, array $app, bool $writeEnv = true): User
    {
        if (amos_installed()) {
            throw new DomainException('AMOS قبلاً نصب شده است. نصب مجدد مجاز نیست.');
        }

        $pdo = self::testConnection($db);
        $this->prepareDatabase($pdo);

        if ($writeEnv) {
            $env = [
                'APP_NAME' => 'AMOS',
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
                'APP_URL' => rtrim($app['url'], '/'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db['host'],
                'DB_PORT' => (string) $db['port'],
                'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => $db['username'],
                'DB_PASSWORD' => $db['password'],
                'FORCE_HTTPS' => str_starts_with($app['url'], 'https://'),
                'SESSION_SECURE_COOKIE' => str_starts_with($app['url'], 'https://'),
            ];
            EnvWriter::set(app()->environmentFilePath(), $env);
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'],
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        try {
            $code = Artisan::call('migrate', ['--force' => true]);
            if ($code !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'migrate exit code '.$code);
            }
            (new ReferenceDataSeeder())->run();
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('AMOS install: database setup failed', ['exception' => $e]);
            throw new DomainException('ساخت جداول دیتابیس ناموفق بود: '.self::safeMessage($e).' — اگر پیام به دسترسی (denied) اشاره دارد، در Manage My Databases کاربر را با ALL PRIVILEGES به دیتابیس اضافه کنید. سپس دوباره «نصب» را بزنید.');
        }

        $user = DB::transaction(function () use ($admin, $app) {
            if (User::where('role', User::ROLE_ADMIN)->exists()) {
                throw new DomainException('مدیر سامانه قبلاً ایجاد شده است.');
            }
            $settings = FundSetting::current();
            if (! empty($app['fund_name'])) {
                $settings->update(['fund_name' => $app['fund_name']]);
            }
            $user = User::create([
                'name' => $admin['name'],
                'username' => mb_strtolower($admin['username']),
                'email' => $admin['email'] ?? null,
                'password' => $admin['password'],
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false, // installer-chosen password
                'joined_at' => now()->toDateString(),
            ]);
            OwnershipAccount::forMember($user);
            AuditLogger::log('system.installed', $user, null, ['admin' => $user->username], $user->id);

            return $user;
        });

        self::writeLock();

        return $user;
    }

    /** Tables created by AMOS migrations (used to recognise leftovers of a failed install). */
    public const TABLES = [
        'migrations', 'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'fund_settings', 'contribution_types', 'ownership_accounts', 'cash_accounts', 'asset_categories', 'assets', 'attachments',
        'ledger_transactions', 'ledger_entries', 'investment_units', 'nav_snapshots', 'contributions', 'monthly_commitments',
        'investment_transactions', 'asset_valuations', 'ideas', 'idea_reviews', 'idea_review_histories', 'idea_status_histories',
        'withdrawal_requests', 'notifications', 'audit_logs',
    ];

    /**
     * Never overwrite real data: refuse a database that has users or tables of another application.
     * Leftovers of a previous FAILED AMOS install (AMOS tables only, no users) are removed so the install can be retried.
     */
    private function prepareDatabase(PDO $pdo): void
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables === []) {
            return;
        }
        if (in_array('users', $tables, true) && (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn() > 0) {
            throw new DomainException('این دیتابیس قبلاً داده دارد. برای جلوگیری از بازنویسی، از یک دیتابیس خالی استفاده کنید.');
        }
        $foreign = array_diff($tables, self::TABLES);
        if ($foreign !== []) {
            throw new DomainException('این دیتابیس جداول برنامه دیگری را دارد ('.implode('، ', array_slice($foreign, 0, 5)).'). برای AMOS یک دیتابیس خالی جدید بسازید.');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`', '', $t).'`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        Log::warning('AMOS install: removed leftover tables of a previous failed install', ['tables' => $tables]);
    }

    public static function safeMessage(Throwable $e): string
    {
        $msg = preg_replace('/\(Connection: .*$/s', '', $e->getMessage());

        return mb_substr(trim((string) $msg), 0, 400);
    }

    public static function writeLock(): void
    {
        $lock = config('amos.lock_file');
        @mkdir(dirname($lock), 0755, true);
        file_put_contents($lock, json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('amos.version')]));
    }

    public static function isDatabaseReady(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }
}
