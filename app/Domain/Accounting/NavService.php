<?php

namespace App\Domain\Accounting;

use App\Models\AssetValuation;
use App\Models\FundSetting;
use App\Models\InvestmentUnit;
use App\Models\LedgerEntry;
use App\Models\NavSnapshot;
use App\Models\OwnershipAccount;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Computes the fund's Net Asset Value from the ledger and approved valuations.
 * NAV = cash + market value of non-cash assets + receivables - payables.
 */
class NavService
{
    public function state(): FundState
    {
        $sum = fn (string $code) => Money::of(
            LedgerEntry::where('account_code', $code)->sum(DB::raw('debit_irr - credit_irr')) ?: 0
        );

        $cashByAccount = LedgerEntry::where('account_code', AccountCode::CASH)
            ->groupBy('cash_account_id')
            ->selectRaw('cash_account_id, SUM(debit_irr - credit_irr) AS bal')
            ->pluck('bal', 'cash_account_id')
            ->map(fn ($v) => Money::of($v))
            ->all();

        $cash = array_reduce($cashByAccount, fn (BigDecimal $c, BigDecimal $v) => $c->plus($v), Money::zero());

        $rows = LedgerEntry::where('account_code', AccountCode::INVESTMENT)
            ->groupBy('asset_id')
            ->selectRaw('asset_id, SUM(quantity) AS qty, SUM(debit_irr - credit_irr) AS cost')
            ->get();

        $holdings = [];
        $assetsCost = Money::zero();
        $assetsMarket = Money::zero();
        foreach ($rows as $row) {
            $qty = Money::of($row->qty);
            $cost = Money::of($row->cost);
            if ($qty->isZero() && $cost->isZero()) {
                continue;
            }
            $valuation = AssetValuation::where('asset_id', $row->asset_id)->where('status', 'approved')
                ->orderByDesc('valuation_date')->orderByDesc('id')->first();
            if ($valuation) {
                $market = Money::irr($qty->multipliedBy(Money::of($valuation->unit_price_irr)));
            } else {
                $market = Money::irr($cost); // no valuation yet: carry at cost
            }
            $holdings[(int) $row->asset_id] = [
                'asset_id' => (int) $row->asset_id,
                'quantity' => $qty,
                'cost' => $cost,
                'market' => $market,
                'unit_price' => $valuation ? Money::of($valuation->unit_price_irr) : null,
                'valuation_date' => $valuation?->valuation_date?->format('Y-m-d'),
                'valued' => (bool) $valuation,
            ];
            $assetsCost = $assetsCost->plus($cost);
            $assetsMarket = $assetsMarket->plus($market);
        }

        $receivables = $sum(AccountCode::RECEIVABLE);
        $payables = $sum(AccountCode::PAYABLE)->negated();
        $nav = Money::irr($cash->plus($assetsMarket)->plus($receivables)->minus($payables));

        $totalUnits = Money::of(InvestmentUnit::sum('units') ?: 0);
        $navPerUnit = $this->navPerUnit($nav, $totalUnits);

        return new FundState(
            cash: Money::irr($cash),
            cashByAccount: $cashByAccount,
            holdings: $holdings,
            assetsCost: Money::irr($assetsCost),
            assetsMarket: Money::irr($assetsMarket),
            receivables: Money::irr($receivables),
            payables: Money::irr($payables),
            nav: $nav,
            totalUnits: $totalUnits,
            navPerUnit: $navPerUnit,
            realizedPnl: $sum(AccountCode::REALIZED_PNL)->negated(),
            otherIncome: $sum(AccountCode::OTHER_INCOME)->negated(),
            expenses: $sum(AccountCode::EXPENSE),
        );
    }

    public function navPerUnit(BigDecimal $nav, BigDecimal $totalUnits): BigDecimal
    {
        if ($totalUnits->isLessThanOrEqualTo(0)) {
            return Money::of(FundSetting::current()->initial_unit_price_irr ?: 1)->toScale(8);
        }

        return $nav->dividedBy($totalUnits, 8, RoundingMode::HalfUp);
    }

    public function unitsOf(OwnershipAccount|int $account): BigDecimal
    {
        $id = $account instanceof OwnershipAccount ? $account->id : $account;

        return Money::of(InvestmentUnit::where('ownership_account_id', $id)->sum('units') ?: 0);
    }

    public function valueOf(OwnershipAccount|int $account, ?FundState $state = null): BigDecimal
    {
        $state ??= $this->state();

        return Money::irr($this->unitsOf($account)->multipliedBy($state->navPerUnit));
    }

    /** Net capital contributed into an ownership account (contributions - withdrawals), from the ledger. */
    public function netCapitalOf(OwnershipAccount $account): BigDecimal
    {
        $code = $account->isPool() ? AccountCode::POOL_CAPITAL : AccountCode::MEMBER_CAPITAL;

        return Money::of(LedgerEntry::where('account_code', $code)->where('ownership_account_id', $account->id)
            ->sum(DB::raw('credit_irr - debit_irr')) ?: 0);
    }

    /** Value of every ownership account. @return array<int, array{account: OwnershipAccount, units: BigDecimal, value: BigDecimal}> */
    public function allAccountValues(?FundState $state = null): array
    {
        $state ??= $this->state();
        $units = InvestmentUnit::groupBy('ownership_account_id')
            ->selectRaw('ownership_account_id, SUM(units) AS u')
            ->pluck('u', 'ownership_account_id');
        $out = [];
        foreach (OwnershipAccount::with('owner')->get() as $acc) {
            $u = Money::of($units[$acc->id] ?? 0);
            $out[$acc->id] = [
                'account' => $acc,
                'units' => $u,
                'value' => Money::irr($u->multipliedBy($state->navPerUnit)),
            ];
        }

        return $out;
    }

    /** Persist today's NAV snapshot (upsert) for history charts and performance. */
    public function recordSnapshot(?FundState $state = null, $date = null): NavSnapshot
    {
        $state ??= $this->state();
        $date = ($date ?? now())->format('Y-m-d');

        return NavSnapshot::updateOrCreate(['snapshot_date' => $date], [
            'cash_irr' => (string) $state->cash,
            'assets_market_irr' => (string) $state->assetsMarket,
            'assets_cost_irr' => (string) $state->assetsCost,
            'receivables_irr' => (string) $state->receivables,
            'payables_irr' => (string) $state->payables,
            'nav_irr' => (string) $state->nav,
            'total_units' => (string) $state->totalUnits->toScale(8),
            'nav_per_unit_irr' => (string) $state->navPerUnit,
            'realized_pnl_irr' => (string) Money::irr($state->realizedPnl),
            'unrealized_pnl_irr' => (string) Money::irr($state->unrealizedPnl()),
        ]);
    }

    /** Serialises all financial postings: lock the single settings row for the transaction duration. */
    public function lockFund(): void
    {
        FundSetting::query()->lockForUpdate()->first();
    }
}
