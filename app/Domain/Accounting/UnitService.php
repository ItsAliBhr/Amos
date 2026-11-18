<?php

namespace App\Domain\Accounting;

use App\Models\InvestmentUnit;
use App\Models\LedgerTransaction;
use App\Models\OwnershipAccount;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Issues and cancels investment units for ownership accounts (members and Pool). */
class UnitService
{
    public function issue(OwnershipAccount $account, BigDecimal $amount, BigDecimal $navPerUnit, Model $source, ?LedgerTransaction $tx): InvestmentUnit
    {
        if ($navPerUnit->isLessThanOrEqualTo(0)) {
            throw new RuntimeException('NAV per unit must be positive to issue units.');
        }
        $units = $amount->dividedBy($navPerUnit, 8, RoundingMode::Down);

        return $this->record($account, $units, $navPerUnit, $amount, $source, $tx);
    }

    public function redeem(OwnershipAccount $account, BigDecimal $units, BigDecimal $navPerUnit, BigDecimal $amount, Model $source, ?LedgerTransaction $tx): InvestmentUnit
    {
        return $this->record($account, $units->negated(), $navPerUnit, $amount->negated(), $source, $tx);
    }

    public function record(OwnershipAccount $account, BigDecimal $units, BigDecimal $navPerUnit, BigDecimal $amount, Model $source, ?LedgerTransaction $tx): InvestmentUnit
    {
        return InvestmentUnit::create([
            'ownership_account_id' => $account->id,
            'units' => (string) $units->toScale(8, RoundingMode::Down),
            'nav_per_unit_irr' => (string) $navPerUnit->toScale(8),
            'amount_irr' => (string) Money::irr($amount),
            'effective_at' => now(),
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'ledger_transaction_id' => $tx?->id,
            'status' => 'posted',
        ]);
    }
}
