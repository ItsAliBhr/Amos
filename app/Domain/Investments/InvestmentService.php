<?php

namespace App\Domain\Investments;

use App\Domain\Accounting\AccountCode;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\NavService;
use App\Domain\DomainException;
use App\Models\Asset;
use App\Models\CashAccount;
use App\Models\Idea;
use App\Models\InvestmentTransaction as Tx;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Buy / sell / expense / income / cash transfer. Economic allocation to ownership baskets
 * happens implicitly through NAV per unit: every basket owns its units' share of the pooled portfolio.
 */
class InvestmentService
{
    public function __construct(private LedgerService $ledger, private NavService $nav)
    {
    }

    public function create(array $data, User $admin): Tx
    {
        $this->assertAdmin($admin);
        if (! empty($data['idempotency_key']) && ($existing = Tx::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }

        $type = $data['type'];
        if (! array_key_exists($type, Tx::TYPES)) {
            throw new DomainException('نوع عملیات نامعتبر است.');
        }

        $qty = Money::of($data['quantity'] ?? 0)->toScale(8, RoundingMode::HalfUp);
        $unitPrice = Money::of($data['unit_price_irr'] ?? 0)->toScale(8, RoundingMode::HalfUp);
        $fee = Money::irr($data['fee_amount_irr'] ?? 0);
        $gross = isset($data['gross_amount_irr']) && $data['gross_amount_irr'] !== null ? Money::irr($data['gross_amount_irr']) : Money::zero();

        if ($fee->isNegative()) {
            throw new DomainException('کارمزد نمی‌تواند منفی باشد.');
        }

        $asset = null;
        if (in_array($type, [Tx::TYPE_BUY, Tx::TYPE_SELL], true)) {
            $asset = Asset::where('id', $data['asset_id'] ?? 0)->where('is_active', true)->first();
            if (! $asset) {
                throw new DomainException('دارایی نامعتبر یا غیرفعال است.');
            }
            if ($qty->isLessThanOrEqualTo(0) || $unitPrice->isLessThanOrEqualTo(0)) {
                throw new DomainException('مقدار و قیمت واحد باید مثبت باشند.');
            }
            $qty = $qty->toScale($asset->decimal_places, RoundingMode::Down)->toScale(8);
            if ($qty->isLessThanOrEqualTo(0)) {
                throw new DomainException('مقدار با توجه به دقت اعشار دارایی معتبر نیست.');
            }
            $gross = Money::irr($qty->multipliedBy($unitPrice));
        } else {
            if (! empty($data['asset_id'])) {
                $asset = Asset::find($data['asset_id']);
            }
            if ($gross->isLessThanOrEqualTo(0)) {
                throw new DomainException('مبلغ باید مثبت باشد.');
            }
            $qty = Money::zero();
            $unitPrice = Money::zero();
        }

        $net = match ($type) {
            Tx::TYPE_BUY, Tx::TYPE_TRANSFER => $gross->plus($fee),
            Tx::TYPE_SELL => $gross->minus($fee),
            default => $gross,
        };
        if ($type === Tx::TYPE_SELL && $net->isNegative()) {
            throw new DomainException('کارمزد بیش از مبلغ فروش است.');
        }
        if (in_array($type, [Tx::TYPE_EXPENSE, Tx::TYPE_INCOME], true)) {
            $fee = Money::zero();
        }

        $cash = CashAccount::where('id', $data['cash_account_id'] ?? 0)->where('is_active', true)->first();
        if (! $cash) {
            throw new DomainException('حساب نقدی نامعتبر است.');
        }
        $toCash = null;
        if ($type === Tx::TYPE_TRANSFER) {
            $toCash = CashAccount::where('id', $data['to_cash_account_id'] ?? 0)->where('is_active', true)->first();
            if (! $toCash || $toCash->id === $cash->id) {
                throw new DomainException('حساب مقصد انتقال نامعتبر است.');
            }
        }

        $tx = DB::transaction(function () use ($data, $admin, $type, $asset, $qty, $unitPrice, $gross, $fee, $net, $cash, $toCash) {
            $tx = Tx::create([
                'asset_id' => $asset?->id,
                'type' => $type,
                'quantity' => (string) $qty,
                'unit_price_irr' => (string) $unitPrice,
                'gross_amount_irr' => (string) $gross,
                'fee_amount_irr' => (string) $fee,
                'net_amount_irr' => (string) $net,
                'transaction_date' => $data['transaction_date'],
                'cash_account_id' => $cash->id,
                'to_cash_account_id' => $toCash?->id,
                'expense_category' => $type === Tx::TYPE_EXPENSE ? ($data['expense_category'] ?? 'other') : null,
                'description' => $data['description'] ?? null,
                'attachment_id' => $data['attachment_id'] ?? null,
                'idea_id' => $data['idea_id'] ?? null,
                'status' => 'pending',
                'submitted_by' => $admin->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
            AuditLogger::log('investment.created', $tx, null, $tx->only(['type', 'asset_id', 'quantity', 'gross_amount_irr', 'fee_amount_irr', 'net_amount_irr']));

            return $tx;
        });

        if (! empty($data['approve_now'])) {
            return $this->approve($tx, $admin);
        }

        return $tx;
    }

    /** @return array{quantity: BigDecimal, cost: BigDecimal} */
    public function holding(int $assetId): array
    {
        $row = LedgerEntry::where('account_code', AccountCode::INVESTMENT)->where('asset_id', $assetId)
            ->selectRaw('COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(debit_irr - credit_irr),0) AS cost')->first();

        return ['quantity' => Money::of($row->qty ?? 0), 'cost' => Money::of($row->cost ?? 0)];
    }

    public function cashBalance(int $cashAccountId): BigDecimal
    {
        return Money::of($this->ledger->balance(AccountCode::CASH, ['cash_account_id' => $cashAccountId]));
    }

    public function approve(Tx $transaction, User $admin): Tx
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($transaction, $admin) {
            $this->nav->lockFund();
            $tx = Tx::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($tx->status !== 'pending') {
                throw new DomainException('این عملیات در وضعیت قابل تأیید نیست.');
            }

            $gross = Money::irr($tx->gross_amount_irr);
            $fee = Money::irr($tx->fee_amount_irr);
            $net = Money::irr($tx->net_amount_irr);
            $qty = Money::of($tx->quantity);
            $cashBal = $this->cashBalance($tx->cash_account_id);
            $assetName = $tx->asset?->name;
            $key = 'investment:'.$tx->id.':approve';
            $costBasis = null;
            $realized = null;

            switch ($tx->type) {
                case Tx::TYPE_BUY:
                    if ($cashBal->isLessThan($net)) {
                        throw new DomainException('نقدینگی حساب انتخابی کافی نیست (موجودی: '.Money::format($cashBal).').');
                    }
                    $desc = "خرید {$assetName} (#{$tx->id})";
                    $ledgerTx = $this->ledger->post('investment_buy', $tx, $tx->transaction_date, $desc, [
                        ['account' => AccountCode::INVESTMENT, 'debit' => $net, 'asset_id' => $tx->asset_id, 'quantity' => $qty, 'description' => $desc],
                        ['account' => AccountCode::CASH, 'credit' => $net, 'cash_account_id' => $tx->cash_account_id, 'description' => $desc],
                    ], $key, $admin);
                    $costBasis = $net;
                    break;

                case Tx::TYPE_SELL:
                    $h = $this->holding($tx->asset_id);
                    if ($h['quantity']->isLessThan($qty)) {
                        throw new DomainException('مقدار فروش بیش از موجودی دارایی است (موجودی: '.Money::formatNumber($h['quantity'], 8).').');
                    }
                    // Weighted average cost for the sold quantity.
                    $costBasis = $h['quantity']->isEqualTo($qty)
                        ? Money::irr($h['cost'])
                        : Money::irr($h['cost']->multipliedBy($qty)->dividedBy($h['quantity'], 8, RoundingMode::HalfUp));
                    $realized = $net->minus($costBasis);
                    $desc = "فروش {$assetName} (#{$tx->id})";
                    $lines = [
                        ['account' => AccountCode::CASH, 'debit' => $net, 'cash_account_id' => $tx->cash_account_id, 'description' => $desc],
                        ['account' => AccountCode::INVESTMENT, 'credit' => $costBasis, 'asset_id' => $tx->asset_id, 'quantity' => $qty->negated(), 'description' => $desc],
                    ];
                    if ($realized->isPositive()) {
                        $lines[] = ['account' => AccountCode::REALIZED_PNL, 'credit' => $realized, 'asset_id' => $tx->asset_id, 'description' => 'سود تحقق‌یافته '.$desc];
                    } elseif ($realized->isNegative()) {
                        $lines[] = ['account' => AccountCode::REALIZED_PNL, 'debit' => $realized->abs(), 'asset_id' => $tx->asset_id, 'description' => 'زیان تحقق‌یافته '.$desc];
                    }
                    $ledgerTx = $this->ledger->post('investment_sell', $tx, $tx->transaction_date, $desc, $lines, $key, $admin);
                    break;

                case Tx::TYPE_EXPENSE:
                    if ($cashBal->isLessThan($net)) {
                        throw new DomainException('نقدینگی حساب انتخابی برای پرداخت هزینه کافی نیست.');
                    }
                    $desc = 'هزینه '.(Tx::EXPENSE_CATEGORIES[$tx->expense_category] ?? '').($assetName ? " - {$assetName}" : ' - کل پرتفوی')." (#{$tx->id})";
                    $ledgerTx = $this->ledger->post('investment_expense', $tx, $tx->transaction_date, $desc, [
                        ['account' => AccountCode::EXPENSE, 'debit' => $net, 'asset_id' => $tx->asset_id, 'description' => $desc],
                        ['account' => AccountCode::CASH, 'credit' => $net, 'cash_account_id' => $tx->cash_account_id, 'description' => $desc],
                    ], $key, $admin);
                    break;

                case Tx::TYPE_INCOME:
                    $desc = 'درآمد'.($assetName ? " - {$assetName}" : '')." (#{$tx->id})";
                    $ledgerTx = $this->ledger->post('investment_income', $tx, $tx->transaction_date, $desc, [
                        ['account' => AccountCode::CASH, 'debit' => $net, 'cash_account_id' => $tx->cash_account_id, 'description' => $desc],
                        ['account' => AccountCode::OTHER_INCOME, 'credit' => $net, 'asset_id' => $tx->asset_id, 'description' => $desc],
                    ], $key, $admin);
                    break;

                case Tx::TYPE_TRANSFER:
                    if ($cashBal->isLessThan($net)) {
                        throw new DomainException('موجودی حساب مبدأ کافی نیست.');
                    }
                    $desc = "انتقال وجه (#{$tx->id})";
                    $lines = [
                        ['account' => AccountCode::CASH, 'debit' => $gross, 'cash_account_id' => $tx->to_cash_account_id, 'description' => $desc],
                        ['account' => AccountCode::CASH, 'credit' => $net, 'cash_account_id' => $tx->cash_account_id, 'description' => $desc],
                    ];
                    if ($fee->isPositive()) {
                        $lines[] = ['account' => AccountCode::EXPENSE, 'debit' => $fee, 'description' => 'کارمزد '.$desc];
                    }
                    $ledgerTx = $this->ledger->post('cash_transfer', $tx, $tx->transaction_date, $desc, $lines, $key, $admin);
                    break;

                default:
                    throw new DomainException('نوع عملیات پشتیبانی نمی‌شود.');
            }

            $tx->forceFill([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'ledger_transaction_id' => $ledgerTx->id,
                'cost_basis_irr' => $costBasis !== null ? (string) $costBasis : null,
                'realized_pnl_irr' => $realized !== null ? (string) $realized : null,
            ])->save();

            if ($tx->idea_id && ($idea = Idea::find($tx->idea_id)) && $idea->status === 'approved') {
                app(\App\Domain\Ideas\IdeaService::class)->markExecuted($idea, $tx, $admin);
            }

            $this->nav->recordSnapshot();
            AuditLogger::log('investment.approved', $tx, ['status' => 'pending'], ['status' => 'approved', 'ledger_transaction_id' => $ledgerTx->id, 'realized_pnl_irr' => $tx->realized_pnl_irr]);

            return $tx;
        });
    }

    public function reject(Tx $tx, User $admin, string $reason): Tx
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل رد الزامی است.');
        }

        return DB::transaction(function () use ($tx, $admin, $reason) {
            $tx = Tx::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->status !== 'pending') {
                throw new DomainException('این عملیات در وضعیت قابل رد نیست.');
            }
            $tx->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            AuditLogger::log('investment.rejected', $tx, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason]);

            return $tx;
        });
    }

    public function reverse(Tx $tx, User $admin, string $reason): Tx
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل برگشت الزامی است.');
        }

        return DB::transaction(function () use ($tx, $admin, $reason) {
            $this->nav->lockFund();
            $tx = Tx::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->status !== 'approved') {
                throw new DomainException('فقط عملیات تأییدشده قابل برگشت است.');
            }
            $original = \App\Models\LedgerTransaction::with('entries')->findOrFail($tx->ledger_transaction_id);
            $rev = $this->ledger->reverse($original, 'investment_reversal', 'برگشت عملیات #'.$tx->id.' - '.$reason, $admin);

            // Invariants after reversal: no negative cash account, no negative holding.
            foreach ($original->entries as $e) {
                if ($e->account_code === AccountCode::CASH && $this->cashBalance($e->cash_account_id)->isNegative()) {
                    throw new DomainException('برگشت این عملیات موجودی حساب نقدی را منفی می‌کند.');
                }
                if ($e->account_code === AccountCode::INVESTMENT && $this->holding($e->asset_id)['quantity']->isNegative()) {
                    throw new DomainException('برگشت این عملیات موجودی دارایی را منفی می‌کند.');
                }
            }

            $tx->forceFill([
                'status' => 'reversed',
                'reversed_by' => $admin->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversal_ledger_transaction_id' => $rev->id,
            ])->save();
            $this->nav->recordSnapshot();
            AuditLogger::log('investment.reversed', $tx, ['status' => 'approved'], ['status' => 'reversed', 'reason' => $reason]);

            return $tx;
        });
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new DomainException('فقط مدیر مجاز به این عملیات است.');
        }
    }
}
