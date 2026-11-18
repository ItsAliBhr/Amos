<?php

namespace Database\Seeders;

use App\Domain\Accounting\CashAccountService;
use App\Domain\Contributions\ContributionService;
use App\Domain\Investments\InvestmentService;
use App\Domain\Members\MemberService;
use App\Domain\Valuations\ValuationService;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT ONLY demo data (the example from the spec: Ali 50, Reza 30, Soroush 20, Pool 10 million Toman).
 * Refuses to run in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo data is not allowed in production.');
        }
        $this->call(ReferenceDataSeeder::class);

        $admin = User::where('username', 'admin')->first();
        if (! $admin) {
            $admin = User::create(['name' => 'مدیر نمونه', 'username' => 'admin', 'password' => 'Admin12345', 'role' => 'admin', 'status' => 'active', 'must_change_password' => false, 'joined_at' => now()->subYear()]);
        }
        $members = app(MemberService::class);
        $mk = function ($name, $username) use ($members, $admin) {
            $u = User::where('username', $username)->first();
            if ($u) {
                return $u;
            }
            [$u] = $members->create(['name' => $name, 'username' => $username, 'password' => 'Member12345', 'joined_at' => now()->subYear()->toDateString()], $admin);
            $u->update(['must_change_password' => false]);

            return $u;
        };
        $ali = $mk('علی', 'ali');
        $reza = $mk('رضا', 'reza');
        $soroush = $mk('سروش', 'soroush');

        $bank = app(CashAccountService::class)->create(['name' => 'حساب بانکی مشترک', 'account_type' => 'bank', 'bank_name' => 'بانک نمونه'], $admin);
        $contrib = app(ContributionService::class);
        $toman = fn ($t) => Money::irr($t * 10);
        foreach ([[$ali, 'extra', 50_000_000], [$reza, 'extra', 30_000_000], [$soroush, 'extra', 20_000_000], [$ali, 'donation', 10_000_000]] as [$u, $type, $amt]) {
            $c = $contrib->create(['member_id' => $u->id, 'type_code' => $type, 'amount_irr' => $toman($amt), 'contribution_date' => now(), 'destination_account_id' => $bank->id], $admin);
            $c->update(['exceeds_cap' => false]);
            $contrib->approve($c, $admin, true);
        }

        $gold = Asset::firstOrCreate(['symbol' => 'GOLD18'], ['name' => 'طلای ۱۸ عیار', 'asset_category_id' => AssetCategory::where('code', 'gold')->value('id'), 'unit_name' => 'گرم', 'decimal_places' => 3]);
        $inv = app(InvestmentService::class);
        $inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '100', 'unit_price_irr' => $toman(1_000_000), 'fee_amount_irr' => 0, 'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $admin);
        app(ValuationService::class)->create(['asset_id' => $gold->id, 'valuation_date' => now(), 'unit_price_irr' => $toman(1_200_000), 'source' => 'نمونه', 'approve_now' => true], $admin);
    }
}
