<?php

namespace App\Domain\Accounting;

use Brick\Math\BigDecimal;

/** Immutable snapshot of fund valuation figures (all amounts in IRR). */
final class FundState
{
    /**
     * @param  array<int, BigDecimal>  $cashByAccount
     * @param  array<int, array{asset_id:int, quantity:BigDecimal, cost:BigDecimal, market:BigDecimal, unit_price:?BigDecimal, valuation_date:?string, valued:bool}>  $holdings
     */
    public function __construct(
        public readonly BigDecimal $cash,
        public readonly array $cashByAccount,
        public readonly array $holdings,
        public readonly BigDecimal $assetsCost,
        public readonly BigDecimal $assetsMarket,
        public readonly BigDecimal $receivables,
        public readonly BigDecimal $payables,
        public readonly BigDecimal $nav,
        public readonly BigDecimal $totalUnits,
        public readonly BigDecimal $navPerUnit,
        public readonly BigDecimal $realizedPnl,
        public readonly BigDecimal $otherIncome,
        public readonly BigDecimal $expenses,
    ) {
    }

    public function unrealizedPnl(): BigDecimal
    {
        return $this->assetsMarket->minus($this->assetsCost);
    }
}
