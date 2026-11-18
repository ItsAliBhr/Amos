<?php

namespace App\Domain\Members;

use App\Domain\DomainException;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberService
{
    public function create(array $data, User $admin): array
    {
        $password = $data['password'] ?? null;
        $password = $password ?: self::generatePassword();

        $user = DB::transaction(function () use ($data, $password, $admin) {
            $user = User::create([
                'name' => $data['name'],
                'username' => mb_strtolower($data['username']),
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'password' => $password,
                'role' => $data['role'] ?? User::ROLE_MEMBER,
                'status' => $data['status'] ?? User::STATUS_ACTIVE,
                'must_change_password' => true,
                'monthly_commitment_irr' => $data['monthly_commitment_irr'] ?? null,
                'joined_at' => $data['joined_at'] ?? now()->toDateString(),
                'admin_note' => $data['admin_note'] ?? null,
            ]);
            OwnershipAccount::forMember($user);
            AuditLogger::log('user.created', $user, null, $user->only(['name', 'username', 'role', 'status']), $admin->id);

            return $user;
        });

        return [$user, $password];
    }

    public function update(User $user, array $data, User $admin): User
    {
        return DB::transaction(function () use ($user, $data, $admin) {
            $newRole = $data['role'] ?? $user->role;
            $newStatus = $data['status'] ?? $user->status;
            if ($user->isAdmin() && $user->isActive() && ($newRole !== User::ROLE_ADMIN || $newStatus !== User::STATUS_ACTIVE)) {
                $this->assertNotLastAdmin($user);
            }
            if (isset($data['username'])) {
                $data['username'] = mb_strtolower($data['username']);
            }
            $user->fill($data);
            $user->save();
            [$old, $new] = AuditLogger::changes($user);
            if ($new) {
                AuditLogger::log('user.updated', $user, $old, $new, $admin->id);
            }
            OwnershipAccount::forMember($user)->update(['name' => 'سبد '.$user->name]);

            return $user;
        });
    }

    public function resetPassword(User $user, User $admin): string
    {
        $password = self::generatePassword();
        $user->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        AuditLogger::log('user.password_reset', $user, null, ['must_change_password' => true], $admin->id);

        return $password;
    }

    public function delete(User $user, User $admin): void
    {
        if ($user->id === $admin->id) {
            throw new DomainException('حذف حساب خودتان مجاز نیست.');
        }
        if ($user->hasFinancialHistory()) {
            throw new DomainException('عضو دارای سابقه مالی قابل حذف نیست؛ به‌جای آن وضعیت را غیرفعال کنید.');
        }
        if ($user->isAdmin() && $user->isActive()) {
            $this->assertNotLastAdmin($user);
        }
        DB::transaction(function () use ($user, $admin) {
            AuditLogger::log('user.deleted', $user, $user->only(['name', 'username', 'role', 'status']), null, $admin->id);
            $user->update(['status' => User::STATUS_INACTIVE]);
            $user->delete(); // soft delete only
        });
    }

    private function assertNotLastAdmin(User $user): void
    {
        $others = User::where('role', User::ROLE_ADMIN)->where('status', User::STATUS_ACTIVE)->where('id', '!=', $user->id)->count();
        if ($others === 0) {
            throw new DomainException('این تنها مدیر فعال سامانه است؛ ابتدا مدیر فعال دیگری تعریف کنید.');
        }
    }

    public static function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out.'!'.random_int(10, 99);
    }
}
