<?php

namespace App\Domain\Accounting;

use App\Domain\DomainException;
use App\Models\CashAccount;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CashAccountService
{
    public function __construct(private LedgerService $ledger, private NavService $nav, private UnitService $units)
    {
    }

    /**
     * Opening balance is posted once through the ledger. Because money already in the account
     * has no individual owner, it is credited to the Pool basket (documented decision; see docs/DECISIONS.md).
     */
    public function create(array $data, User $admin): CashAccount
    {
        return DB::transaction(function () use ($data, $admin) {
            $opening = Money::irr($data['opening_balance_irr'] ?? 0);
            if ($opening->isNegative()) {
                throw new DomainException('موجودی افتتاحیه نمی‌تواند منفی باشد.');
            }
            $acc = CashAccount::create([
                'name' => $data['name'],
                'account_type' => $data['account_type'] ?? 'bank',
                'bank_name' => $data['bank_name'] ?? null,
                'account_number_encrypted' => $data['account_number'] ?? null,
                'opening_balance_irr' => (string) $opening,
                'is_active' => $data['is_active'] ?? true,
            ]);
            if ($opening->isPositive()) {
                $this->nav->lockFund();
                $state = $this->nav->state();
                $pool = OwnershipAccount::pool();
                $desc = 'موجودی افتتاحیه حساب '.$acc->name;
                $tx = $this->ledger->post('opening_balance', $acc, now(), $desc, [
                    ['account' => AccountCode::CASH, 'debit' => $opening, 'cash_account_id' => $acc->id, 'description' => $desc],
                    ['account' => AccountCode::POOL_CAPITAL, 'credit' => $opening, 'ownership_account_id' => $pool->id, 'description' => $desc],
                ], 'cash_account:'.$acc->id.':opening', $admin);
                $this->units->issue($pool, $opening, $state->navPerUnit, $acc, $tx);
                $acc->forceFill(['opening_ledger_transaction_id' => $tx->id])->save();
                $this->nav->recordSnapshot();
            }
            AuditLogger::log('cash_account.created', $acc, null, $acc->only(['name', 'account_type', 'bank_name', 'opening_balance_irr']));

            return $acc;
        });
    }

    public function update(CashAccount $acc, array $data, User $admin): CashAccount
    {
        $fill = collect($data)->only(['name', 'account_type', 'bank_name', 'is_active'])->all();
        if (array_key_exists('account_number', $data) && $data['account_number'] !== null && $data['account_number'] !== '') {
            $fill['account_number_encrypted'] = $data['account_number'];
        }
        if (isset($fill['is_active']) && ! $fill['is_active'] && ! Money::of($this->ledger->balance(AccountCode::CASH, ['cash_account_id' => $acc->id]))->isZero()) {
            throw new DomainException('حساب دارای موجودی را نمی‌توان غیرفعال کرد؛ ابتدا موجودی را منتقل کنید.');
        }
        $acc->fill($fill)->save();
        [$old, $new] = AuditLogger::changes($acc);
        if ($new) {
            AuditLogger::log('cash_account.updated', $acc, $old, $new);
        }

        return $acc;
    }
}
