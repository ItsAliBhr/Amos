<?php

namespace App\Console\Commands;

use App\Models\OwnershipAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

/** Emergency/extra admin creation from the command line (e.g. lost admin password). */
class CreateAdminCommand extends Command
{
    protected $signature = 'amos:create-admin {username} {--name=} {--password=}';

    protected $description = 'Create an admin user (or reset an existing user to admin with a new password)';

    public function handle(): int
    {
        $username = mb_strtolower($this->argument('username'));
        $password = $this->option('password') ?: $this->secret('Password');
        if (strlen((string) $password) < 10) {
            $this->error('Password must be at least 10 characters.');

            return self::FAILURE;
        }
        $user = User::withTrashed()->where('username', $username)->first();
        if ($user) {
            $user->restore();
            $user->forceFill(['role' => 'admin', 'status' => 'active', 'password' => $password, 'must_change_password' => true])->save();
            AuditLogger::log('cli.admin_reset', $user, null, ['username' => $username], null);
        } else {
            $user = User::create(['name' => $this->option('name') ?: $username, 'username' => $username, 'password' => $password,
                'role' => 'admin', 'status' => 'active', 'must_change_password' => true, 'joined_at' => now()->toDateString()]);
            OwnershipAccount::forMember($user);
            AuditLogger::log('cli.admin_created', $user, null, ['username' => $username], null);
        }
        $this->info("Admin '{$username}' is ready. Password change will be required at first login.");

        return self::SUCCESS;
    }
}
