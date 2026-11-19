<?php

namespace Tests\Feature;

use App\Domain\DomainException;
use App\Services\Install\Installer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;

/**
 * Exercises the web installer end-to-end against a scratch database (amos_install_test).
 * Does not use RefreshDatabase because the installer reconfigures the connection itself.
 */
class InstallerTest extends BaseTestCase
{
    private string $dir;
    private string $db = 'amos_install_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/amos-install-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        copy(base_path('.env.example'), $this->dir.'/.env');
        $this->app->useEnvironmentPath($this->dir);
        config(['amos.installed' => null, 'amos.lock_file' => $this->dir.'/installed.lock']);

        $pdo = $this->pdo();
        $pdo->exec("DROP DATABASE IF EXISTS `{$this->db}`");
        $pdo->exec("CREATE DATABASE `{$this->db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    protected function tearDown(): void
    {
        $this->pdo()->exec("DROP DATABASE IF EXISTS `{$this->db}`");
        foreach (array_merge(glob($this->dir.'/*') ?: [], glob($this->dir.'/.env*') ?: []) as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function pdo(): PDO
    {
        return new PDO('mysql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', 3306), env('DB_USERNAME'), env('DB_PASSWORD'));
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'db_host' => env('DB_HOST', '127.0.0.1'), 'db_port' => env('DB_PORT', 3306), 'db_database' => $this->db,
            'db_username' => env('DB_USERNAME'), 'db_password' => env('DB_PASSWORD'),
            'app_url' => 'https://amos.example.com', 'fund_name' => 'صندوق آزمایشی',
            'admin_name' => 'مدیر', 'admin_username' => 'Boss', 'admin_password' => 'StrongPass123', 'admin_password_confirmation' => 'StrongPass123',
        ], $override);
    }

    public function test_uninstalled_app_redirects_to_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('نصب AMOS');
    }

    public function test_web_installer_creates_schema_admin_and_locks_itself(): void
    {
        $this->post('/install', $this->payload())->assertRedirect('/');

        $this->assertFileExists($this->dir.'/installed.lock');
        $env = file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('DB_DATABASE='.$this->db, $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('APP_ENV=production', $env);

        $pdo = $this->pdo();
        $pdo->exec("USE `{$this->db}`");
        $admin = $pdo->query("SELECT * FROM users WHERE username='boss'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('admin', $admin['role']);
        $this->assertTrue(password_verify('StrongPass123', $admin['password']));
        $this->assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM contribution_types')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM ownership_accounts WHERE owner_type='pool'")->fetchColumn());
        $this->assertSame('صندوق آزمایشی', $pdo->query('SELECT fund_name FROM fund_settings')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM contributions')->fetchColumn(), 'no demo data');
        // Every table must be InnoDB (transactions + foreign keys), even if the server defaults to MyISAM.
        $this->assertSame([], $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='{$this->db}' AND engine <> 'InnoDB'")->fetchAll(PDO::FETCH_COLUMN));

        // Locked: the installer is no longer reachable and cannot run again.
        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->payload())->assertNotFound();
        $this->expectException(DomainException::class);
        app(Installer::class)->install(['host' => '127.0.0.1', 'port' => 3306, 'database' => $this->db, 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD')],
            ['name' => 'x', 'username' => 'y', 'password' => 'StrongPass123'], ['url' => 'https://x']);
    }

    public function test_installer_never_overwrites_a_database_with_data(): void
    {
        $this->post('/install', $this->payload())->assertRedirect('/');
        unlink($this->dir.'/installed.lock'); // simulate someone deleting the lock file

        try {
            app(Installer::class)->install(['host' => env('DB_HOST', '127.0.0.1'), 'port' => 3306, 'database' => $this->db, 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD')],
                ['name' => 'x', 'username' => 'intruder', 'password' => 'StrongPass123'], ['url' => 'https://x'], writeEnv: false);
            $this->fail('Installer must refuse a non-empty database');
        } catch (DomainException $e) {
            $this->assertStringContainsString('داده دارد', $e->getMessage());
        }
        $pdo = $this->pdo();
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM `{$this->db}`.users WHERE username='intruder'")->fetchColumn());
    }

    public function test_install_can_be_retried_after_a_failed_attempt(): void
    {
        // Simulate leftovers of a failed install: some AMOS tables, no users.
        $pdo = $this->pdo();
        $pdo->exec("USE `{$this->db}`");
        $pdo->exec('CREATE TABLE migrations (id INT)');
        $pdo->exec('CREATE TABLE users (id INT)');
        $pdo->exec('CREATE TABLE fund_settings (id INT)');

        $this->post('/install', $this->payload())->assertRedirect('/');
        $this->assertFileExists($this->dir.'/installed.lock');
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM `{$this->db}`.users WHERE username='boss'")->fetchColumn());
    }

    public function test_installer_refuses_database_of_another_application(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("CREATE TABLE `{$this->db}`.wp_posts (id INT)");
        $this->post('/install', $this->payload())->assertSessionHas('error');
        $this->assertFileDoesNotExist($this->dir.'/installed.lock');
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$this->db}'")->fetchColumn());
    }

    public function test_installer_rejects_weak_admin_password_and_bad_db_credentials(): void
    {
        $this->post('/install', $this->payload(['admin_password' => 'weak', 'admin_password_confirmation' => 'weak']))->assertSessionHasErrors('admin_password');
        $this->post('/install', $this->payload(['db_password' => 'wrong-password']))->assertSessionHas('error');
        $this->assertFileDoesNotExist($this->dir.'/installed.lock');
    }
}
