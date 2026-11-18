<?php

namespace Tests\Feature;

use App\Domain\Accounting\AccountCode;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\NavService;
use App\Domain\Contributions\ContributionService;
use App\Domain\DomainException;
use App\Domain\Investments\InvestmentService;
use App\Domain\Valuations\ValuationService;
use App\Domain\Withdrawals\WithdrawalService;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetValuation;
use App\Models\Contribution;
use App\Models\FundSetting;
use App\Models\InvestmentUnit;
use App\Models\LedgerEntry;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Support\Money;
use Tests\TestCase;

class FinancialTest extends TestCase
{
    private ContributionService $contrib;
    private NavService $nav;
    private InvestmentService $inv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contrib = app(ContributionService::class);
        $this->nav = app(NavService::class);
        $this->inv = app(InvestmentService::class);
    }

    private function contribute(User $member, string $type, int $toman, int $bankId, bool $approve = true, bool $override = true): Contribution
    {
        $c = $this->contrib->create([
            'member_id' => $member->id, 'type_code' => $type, 'amount_irr' => $this->t($toman),
            'contribution_date' => now(), 'destination_account_id' => $bankId,
        ], $this->admin);

        return $approve ? $this->contrib->approve($c, $this->admin, $override) : $c;
    }

    private function asset(string $symbol = 'GOLD', int $decimals = 3): Asset
    {
        return Asset::create(['name' => $symbol, 'symbol' => $symbol, 'asset_category_id' => AssetCategory::first()->id, 'unit_name' => 'g', 'decimal_places' => $decimals]);
    }

    private function units(User|OwnershipAccount $who): string
    {
        $acc = $who instanceof User ? OwnershipAccount::forMember($who) : $who;

        return (string) $this->nav->unitsOf($acc)->toScale(8);
    }

    public function test_member_contribution_issues_units_to_member_only(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'monthly', 5_000_000, $bank->id);

        $this->assertDecimalEquals($this->t(5_000_000), $this->units($ali), '', 8);
        $this->assertDecimalEquals(0, $this->units(OwnershipAccount::pool()), '', 8);
        $this->assertDecimalEquals($this->t(5_000_000), $this->nav->state()->cash);
    }

    public function test_donation_only_increases_pool_units_and_gives_donor_no_share(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'donation', 2_000_000, $bank->id);

        $this->assertDecimalEquals(0, $this->units($ali), '', 8);
        $this->assertDecimalEquals($this->t(2_000_000), $this->units(OwnershipAccount::pool()), '', 8);
        $this->assertSame(0, LedgerEntry::where('account_code', AccountCode::MEMBER_CAPITAL)->count());
    }

    public function test_pending_and_rejected_contributions_have_no_financial_effect(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $pending = $this->contribute($ali, 'monthly', 1_000_000, $bank->id, approve: false);
        $rejected = $this->contribute($ali, 'extra', 1_000_000, $bank->id, approve: false);
        $this->contrib->reject($rejected, $this->admin, 'رسید نامعتبر');

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(0, InvestmentUnit::count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertTrue($this->nav->state()->nav->isZero());
    }

    public function test_rejection_requires_reason(): void
    {
        $bank = $this->cashAccount();
        $c = $this->contribute($this->member(), 'monthly', 1000, $bank->id, approve: false);
        $this->expectException(DomainException::class);
        $this->contrib->reject($c, $this->admin, '  ');
    }

    public function test_monthly_cap_requires_override_and_excludes_donations(): void
    {
        FundSetting::current()->update(['monthly_contribution_cap_irr' => (string) $this->t(10_000_000), 'cap_mode' => 'require_override']);
        $bank = $this->cashAccount();
        $ali = $this->member('ali');

        $this->contribute($ali, 'monthly', 6_000_000, $bank->id, override: false);
        // Donation of any size is outside the cap.
        $don = $this->contribute($ali, 'donation', 50_000_000, $bank->id, override: false);
        $this->assertSame('approved', $don->status);
        $this->assertFalse($don->exceeds_cap);

        $extra = $this->contribute($ali, 'extra', 5_000_000, $bank->id, approve: false);
        $this->assertTrue($extra->exceeds_cap);
        try {
            $this->contrib->approve($extra, $this->admin, false);
            $this->fail('Approval over cap without override must fail');
        } catch (DomainException) {
            $this->assertSame('pending', $extra->fresh()->status);
        }
        $approved = $this->contrib->approve($extra, $this->admin, true);
        $this->assertTrue($approved->cap_override);
    }

    public function test_cap_block_mode_rejects_submission(): void
    {
        FundSetting::current()->update(['monthly_contribution_cap_irr' => (string) $this->t(10_000_000), 'cap_mode' => 'block']);
        $bank = $this->cashAccount();
        $this->expectException(DomainException::class);
        $this->contribute($this->member(), 'extra', 10_000_001, $bank->id, approve: false);
    }

    public function test_buy_reduces_cash_and_increases_asset(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 10_000_000, $bank->id);
        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '2.5', 'unit_price_irr' => $this->t(2_000_000),
            'fee_amount_irr' => $this->t(10_000), 'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);

        $state = $this->nav->state();
        $this->assertDecimalEquals($this->t(10_000_000 - 5_000_000 - 10_000), $state->cash);
        $this->assertDecimalEquals('2.5', $state->holdings[$gold->id]['quantity'], '', 3);
        $this->assertDecimalEquals($this->t(5_010_000), $state->holdings[$gold->id]['cost']);
        // Without valuation, assets are carried at cost: buying alone only changes NAV by the fee.
        $this->assertDecimalEquals($this->t(10_000_000), $state->nav);
    }

    public function test_buy_with_insufficient_cash_fails(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 1_000, $bank->id);
        $gold = $this->asset();
        $this->expectException(DomainException::class);
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '1', 'unit_price_irr' => $this->t(2_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
    }

    public function test_sell_uses_weighted_average_cost_and_records_realized_pnl(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 100_000_000, $bank->id);
        $gold = $this->asset();
        foreach ([[10, 1_000_000], [10, 2_000_000]] as [$q, $p]) {
            $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => (string) $q, 'unit_price_irr' => $this->t($p),
                'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        }
        // Average cost = 1.5M per unit. Sell 5 @ 2.2M with 100k fee: proceeds 10.9M, cost 7.5M -> +3.4M.
        $sell = $this->inv->create(['type' => 'sell', 'asset_id' => $gold->id, 'quantity' => '5', 'unit_price_irr' => $this->t(2_200_000),
            'fee_amount_irr' => $this->t(100_000), 'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);

        $this->assertDecimalEquals($this->t(7_500_000), $sell->cost_basis_irr);
        $this->assertDecimalEquals($this->t(3_400_000), $sell->realized_pnl_irr);
        $state = $this->nav->state();
        $this->assertDecimalEquals('15', $state->holdings[$gold->id]['quantity'], '', 3);
        $this->assertDecimalEquals($this->t(22_500_000), $state->holdings[$gold->id]['cost']);
        $this->assertDecimalEquals($this->t(100_000_000 - 30_000_000 + 10_900_000), $state->cash);
        $this->assertDecimalEquals($this->t(3_400_000), $state->realizedPnl);
        $this->assertSame([], app(LedgerService::class)->verifyIntegrity());
    }

    public function test_selling_more_than_holding_is_impossible(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 10_000_000, $bank->id);
        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '1', 'unit_price_irr' => $this->t(1_000_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);

        $this->expectException(DomainException::class);
        $this->inv->create(['type' => 'sell', 'asset_id' => $gold->id, 'quantity' => '1.001', 'unit_price_irr' => $this->t(1_000_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
    }

    public function test_expense_reduces_net_asset_value(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 1_000_000, $bank->id);
        $this->inv->create(['type' => 'expense', 'gross_amount_irr' => $this->t(50_000), 'expense_category' => 'custody',
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);

        $state = $this->nav->state();
        $this->assertDecimalEquals($this->t(950_000), $state->nav);
        $this->assertDecimalEquals($this->t(50_000), $state->expenses);
        $this->assertDecimalEquals('0.95', $state->navPerUnit, '', 8);
    }

    public function test_cash_transfer_between_accounts_creates_no_pnl(): void
    {
        $a = $this->cashAccount('A');
        $b = $this->cashAccount('B');
        $this->contribute($this->member(), 'extra', 1_000_000, $a->id);
        $this->inv->create(['type' => 'transfer', 'gross_amount_irr' => $this->t(400_000), 'transaction_date' => now(),
            'cash_account_id' => $a->id, 'to_cash_account_id' => $b->id, 'approve_now' => true], $this->admin);
        $state = $this->nav->state();
        $this->assertDecimalEquals($this->t(600_000), $state->cashByAccount[$a->id]);
        $this->assertDecimalEquals($this->t(400_000), $state->cashByAccount[$b->id]);
        $this->assertDecimalEquals($this->t(1_000_000), $state->nav);
    }

    public function test_new_valuation_keeps_history_and_latest_approved_drives_nav(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 10_000_000, $bank->id);
        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '10', 'unit_price_irr' => $this->t(1_000_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        $vs = app(ValuationService::class);
        $v1 = $vs->create(['asset_id' => $gold->id, 'valuation_date' => now()->subDay(), 'unit_price_irr' => $this->t(1_100_000), 'approve_now' => true], $this->admin);
        $v2 = $vs->create(['asset_id' => $gold->id, 'valuation_date' => now(), 'unit_price_irr' => $this->t(1_200_000), 'approve_now' => true], $this->admin);
        $vs->create(['asset_id' => $gold->id, 'valuation_date' => now(), 'unit_price_irr' => $this->t(9_999_999)], $this->admin); // pending: ignored

        $this->assertSame(3, AssetValuation::count());
        $this->assertDecimalEquals($this->t(1_100_000), $v1->fresh()->unit_price_irr);
        $this->assertDecimalEquals($this->t(12_000_000), $this->nav->state()->assetsMarket);
        $this->assertDecimalEquals($this->t(2_000_000), $this->nav->state()->unrealizedPnl());
        $this->assertDecimalEquals($this->t(12_000_000), $v2->total_value_irr);
    }

    /** Spec example: Ali 50, Reza 30, Soroush 20, Pool 10 million Toman; +20% portfolio growth. */
    public function test_profit_is_allocated_to_baskets_by_units_and_pool_profit_stays_in_pool(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $reza = $this->member('reza');
        $soroush = $this->member('soroush');
        $this->contribute($ali, 'extra', 50_000_000, $bank->id);
        $this->contribute($reza, 'extra', 30_000_000, $bank->id);
        $this->contribute($soroush, 'extra', 20_000_000, $bank->id);
        $this->contribute($ali, 'donation', 10_000_000, $bank->id);

        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '110', 'unit_price_irr' => $this->t(1_000_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        app(ValuationService::class)->create(['asset_id' => $gold->id, 'valuation_date' => now(), 'unit_price_irr' => $this->t(1_200_000), 'approve_now' => true], $this->admin);

        $state = $this->nav->state();
        $this->assertDecimalEquals($this->t(132_000_000), $state->nav);
        $this->assertDecimalEquals('1.2', $state->navPerUnit, '', 8);
        $this->assertDecimalEquals($this->t(60_000_000), $this->nav->valueOf(OwnershipAccount::forMember($ali), $state));
        $this->assertDecimalEquals($this->t(36_000_000), $this->nav->valueOf(OwnershipAccount::forMember($reza), $state));
        $this->assertDecimalEquals($this->t(24_000_000), $this->nav->valueOf(OwnershipAccount::forMember($soroush), $state));
        $this->assertDecimalEquals($this->t(12_000_000), $this->nav->valueOf(OwnershipAccount::pool(), $state));

        // A later contribution is priced at the new NAV per unit: it neither dilutes nor gains existing profit.
        $late = $this->member('late');
        $c = $this->contribute($late, 'extra', 12_000_000, $bank->id);
        $this->assertDecimalEquals('1.2', $c->nav_per_unit_irr, '', 8);
        $this->assertDecimalEquals($this->t(12_000_000), $this->nav->valueOf(OwnershipAccount::forMember($late)));
        $this->assertDecimalEquals($this->t(60_000_000), $this->nav->valueOf(OwnershipAccount::forMember($ali)));
    }

    public function test_withdrawal_cannot_exceed_basket_value(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'extra', 1_000_000, $bank->id);
        $this->expectException(DomainException::class);
        app(WithdrawalService::class)->request($ali, $this->t(1_000_001), null);
    }

    public function test_withdrawal_request_alone_does_not_reduce_balance_and_payment_redeems_units(): void
    {
        FundSetting::current()->update(['withdrawal_fee_percent' => 1]);
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'extra', 1_000_000, $bank->id);
        $ws = app(WithdrawalService::class);
        $w = $ws->request($ali, $this->t(400_000), 'need cash');

        $this->assertDecimalEquals($this->t(1_000_000), $this->units($ali), '', 8);
        $ws->approve($w, $this->admin, null, null);
        $paid = $ws->pay($w, $this->admin, $bank->id);

        $this->assertSame('paid', $paid->status);
        $this->assertDecimalEquals($this->t(4_000), $paid->fee_irr);
        $this->assertDecimalEquals($this->t(396_000), $paid->net_paid_irr);
        $this->assertDecimalEquals($this->t(600_000), $this->units($ali), '', 8);
        // Fee stays in the fund (other income) -> NAV = 1,000,000 - 396,000.
        $this->assertDecimalEquals($this->t(604_000), $this->nav->state()->nav);
        $this->assertSame([], app(LedgerService::class)->verifyIntegrity());
    }

    public function test_withdrawal_waits_for_liquidity_when_cash_is_invested(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'extra', 1_000_000, $bank->id);
        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '1', 'unit_price_irr' => $this->t(900_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        $ws = app(WithdrawalService::class);
        $w = $ws->approve($ws->request($ali, $this->t(500_000), null), $this->admin, null, null);
        $this->assertSame('awaiting_liquidity', $w->status);
        try {
            $ws->pay($w, $this->admin, $bank->id);
            $this->fail('Payment without liquidity must not succeed');
        } catch (DomainException) {
            $this->assertSame('awaiting_liquidity', $w->fresh()->status);
            $this->assertDecimalEquals($this->t(1_000_000), $this->units($ali), '', 8);
        }
    }

    public function test_full_withdrawal_redeems_all_units(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $this->contribute($ali, 'extra', 333_333, $bank->id);
        $this->inv->create(['type' => 'expense', 'gross_amount_irr' => '7', 'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);
        $ws = app(WithdrawalService::class);
        $value = $this->nav->valueOf(OwnershipAccount::forMember($ali));
        $w = $ws->approve($ws->request($ali, $value, null), $this->admin, null, null);
        $ws->pay($w, $this->admin, $bank->id);
        $this->assertDecimalEquals(0, $this->units($ali), '', 8);
    }

    public function test_approval_is_idempotent_and_double_submit_creates_one_record(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $data = ['member_id' => $ali->id, 'type_code' => 'monthly', 'amount_irr' => $this->t(1000), 'contribution_date' => now(),
            'destination_account_id' => $bank->id, 'idempotency_key' => 'same-key'];
        $c1 = $this->contrib->create($data, $this->admin);
        $c2 = $this->contrib->create($data, $this->admin);
        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(1, Contribution::count());

        $this->contrib->approve($c1, $this->admin);
        try {
            $this->contrib->approve($c1, $this->admin);
            $this->fail('Second approval must fail');
        } catch (DomainException) {
        }
        $this->assertSame(1, InvestmentUnit::count());
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_reversal_creates_mirror_document_and_cancels_units(): void
    {
        $bank = $this->cashAccount();
        $ali = $this->member('ali');
        $c = $this->contribute($ali, 'extra', 1_000_000, $bank->id);
        $this->contrib->reverse($c, $this->admin, 'ثبت اشتباه');

        $this->assertSame('reversed', $c->fresh()->status);
        $this->assertSame(4, LedgerEntry::count()); // original kept + reversal
        $this->assertDecimalEquals(0, $this->units($ali), '', 8);
        $this->assertTrue($this->nav->state()->nav->isZero());
        $this->assertSame([], app(LedgerService::class)->verifyIntegrity());
    }

    public function test_posted_ledger_rows_are_immutable(): void
    {
        $bank = $this->cashAccount();
        $this->contribute($this->member(), 'extra', 1000, $bank->id);
        $this->expectException(\LogicException::class);
        LedgerEntry::first()->update(['debit_irr' => 1]);
    }

    public function test_balances_are_rebuildable_from_ledger(): void
    {
        $bank = $this->cashAccount('Bank', (string) $this->t(5_000)); // opening balance -> Pool
        $ali = $this->member('ali');
        $this->contribute($ali, 'extra', 20_000, $bank->id);
        $gold = $this->asset();
        $this->inv->create(['type' => 'buy', 'asset_id' => $gold->id, 'quantity' => '3', 'unit_price_irr' => $this->t(5_000),
            'transaction_date' => now(), 'cash_account_id' => $bank->id, 'approve_now' => true], $this->admin);

        $cash = Money::of(LedgerEntry::where('account_code', AccountCode::CASH)->sum('debit_irr'))
            ->minus(Money::of(LedgerEntry::where('account_code', AccountCode::CASH)->sum('credit_irr')));
        $this->assertDecimalEquals($this->t(10_000), $cash);
        $this->assertDecimalEquals($this->t(5_000), $this->nav->valueOf(OwnershipAccount::pool()));
        $this->assertDecimalEquals($this->t(25_000), $this->nav->state()->nav);
        $this->assertSame([], app(LedgerService::class)->verifyIntegrity());
    }

    public function test_amounts_are_stored_as_exact_decimals(): void
    {
        $bank = $this->cashAccount();
        $c = $this->contrib->create(['member_id' => $this->member()->id, 'type_code' => 'extra', 'amount_irr' => Money::of('123456789012345678.99'),
            'contribution_date' => now(), 'destination_account_id' => $bank->id], $this->admin);
        $this->assertSame('123456789012345678.99', (string) $c->fresh()->amount_irr);
    }
}
