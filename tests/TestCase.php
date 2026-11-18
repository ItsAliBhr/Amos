<?php

namespace Tests;

use App\Domain\Accounting\CashAccountService;
use App\Domain\Members\MemberService;
use App\Models\CashAccount;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FundSetting::flushCache();
        (new ReferenceDataSeeder())->run();
        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin', 'password' => 'Secret12345', 'role' => 'admin',
            'status' => 'active', 'must_change_password' => false, 'joined_at' => now()->subYear(),
        ]);
        OwnershipAccount::forMember($this->admin);
    }

    protected function member(string $username = 'ali', array $attrs = []): User
    {
        [$u] = app(MemberService::class)->create(array_merge([
            'name' => ucfirst($username), 'username' => $username, 'password' => 'Member12345',
            'joined_at' => now()->subYear()->toDateString(),
        ], $attrs), $this->admin);
        $u->forceFill(['must_change_password' => false])->save();

        return $u->fresh();
    }

    protected function cashAccount(string $name = 'Bank', int|string $openingIrr = 0): CashAccount
    {
        return app(CashAccountService::class)->create(['name' => $name, 'opening_balance_irr' => $openingIrr], $this->admin);
    }

    /** Toman -> IRR BigDecimal. */
    protected function t(int|string $toman): BigDecimal
    {
        return Money::irr(Money::of($toman)->multipliedBy(10));
    }

    protected function assertDecimalEquals(mixed $expected, mixed $actual, string $message = '', int $scale = 2): void
    {
        $this->assertSame(Money::str($expected, $scale), Money::str($actual, $scale), $message);
    }
}
