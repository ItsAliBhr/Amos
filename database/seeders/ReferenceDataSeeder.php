<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\ContributionType;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use Illuminate\Database\Seeder;

/** Base data required in every installation (safe for production; idempotent; no demo data). */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => ContributionType::MONTHLY, 'name' => 'واریزی ثابت ماهانه', 'ownership_type' => 'member', 'counts_toward_cap' => true, 'counts_toward_commitment' => true, 'admin_only' => false],
            ['code' => ContributionType::EXTRA, 'name' => 'واریزی اضافه (غیر اهدایی)', 'ownership_type' => 'member', 'counts_toward_cap' => true, 'counts_toward_commitment' => false, 'admin_only' => false],
            ['code' => ContributionType::DONATION, 'name' => 'کمک اهدایی به Pool', 'ownership_type' => 'pool', 'counts_toward_cap' => false, 'counts_toward_commitment' => false, 'admin_only' => false],
            ['code' => ContributionType::ADJUSTMENT, 'name' => 'سند اصلاحی (فقط مدیر)', 'ownership_type' => 'member', 'counts_toward_cap' => false, 'counts_toward_commitment' => false, 'admin_only' => true],
        ];
        foreach ($types as $t) {
            ContributionType::updateOrCreate(['code' => $t['code']], $t + ['is_active' => true]);
        }

        $categories = [
            'gold' => 'طلا', 'currency' => 'ارز', 'stock' => 'سهام', 'crypto' => 'رمزارز',
            'commodity' => 'کالا', 'deposit' => 'سپرده', 'other' => 'سایر',
        ];
        foreach ($categories as $code => $name) {
            AssetCategory::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        if (! FundSetting::query()->exists()) {
            FundSetting::create([
                'fund_name' => 'AMOS',
                'monthly_contribution_cap_irr' => '100000000.00', // 10,000,000 Toman
                'initial_unit_price_irr' => '1',
            ]);
        }

        OwnershipAccount::pool();
    }
}
