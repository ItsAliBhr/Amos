<?php

namespace App\Domain\Withdrawals;

use App\Domain\Accounting\AccountCode;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\NavService;
use App\Domain\Accounting\UnitService;
use App\Domain\DomainException;
use App\Models\CashAccount;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Models\WithdrawalRequest as W;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * A request alone never reduces balances. Units are cancelled and cash leaves the fund
 * only at payment time, inside one database transaction.
 */
class WithdrawalService
{
    private const OPEN = ['pending', 'approved', 'awaiting_liquidity'];

    public function __construct(private LedgerService $ledger, private NavService $nav, private UnitService $units)
    {
    }

    public function request(User $member, BigDecimal $amount, ?string $note, ?string $idempotencyKey = null): W
    {
        if ($idempotencyKey && ($existing = W::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        $settings = FundSetting::current();
        if (! $settings->withdrawal_enabled) {
            throw new DomainException('امکان ثبت درخواست برداشت در حال حاضر غیرفعال است.');
        }
        $amount = Money::irr($amount);
        if ($amount->isLessThanOrEqualTo(0)) {
            throw new DomainException('مبلغ برداشت باید مثبت باشد.');
        }
        if ($amount->isLessThan(Money::of($settings->withdrawal_min_irr))) {
            throw new DomainException('مبلغ کمتر از حداقل برداشت مجاز است.');
        }
        $account = OwnershipAccount::forMember($member);
        $this->assertWithinValue($account, $amount, null);

        return DB::transaction(function () use ($member, $account, $amount, $note, $idempotencyKey) {
            $w = W::create([
                'member_id' => $member->id,
                'ownership_account_id' => $account->id,
                'requested_amount_irr' => (string) $amount,
                'status' => 'pending',
                'request_note' => $note,
                'requested_at' => now(),
                'submitted_by' => $member->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            AuditLogger::log('withdrawal.requested', $w, null, $w->only(['member_id', 'requested_amount_irr']));
            Notifier::admins('withdrawal.new', 'درخواست برداشت جدید', $member->name.' - '.Money::format($amount), $w, $member->id);

            return $w;
        });
    }

    /** Pool withdrawals are recorded only by an admin, with a mandatory reason. */
    public function requestPool(User $admin, BigDecimal $amount, string $reason, ?string $idempotencyKey = null): W
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل برداشت از Pool الزامی است.');
        }
        if ($idempotencyKey && ($existing = W::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        $amount = Money::irr($amount);
        if ($amount->isLessThanOrEqualTo(0)) {
            throw new DomainException('مبلغ برداشت باید مثبت باشد.');
        }
        $pool = OwnershipAccount::pool();
        $this->assertWithinValue($pool, $amount, null);

        return DB::transaction(function () use ($admin, $pool, $amount, $reason, $idempotencyKey) {
            $w = W::create([
                'member_id' => null,
                'ownership_account_id' => $pool->id,
                'requested_amount_irr' => (string) $amount,
                'approved_amount_irr' => (string) $amount,
                'status' => 'approved',
                'request_note' => $reason,
                'admin_note' => $reason,
                'requested_at' => now(),
                'submitted_by' => $admin->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);
            AuditLogger::log('withdrawal.pool_requested', $w, null, ['amount' => (string) $amount, 'reason' => $reason]);

            return $w;
        });
    }

    public function approve(W $request, User $admin, ?BigDecimal $approvedAmount, ?string $note): W
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($request, $admin, $approvedAmount, $note) {
            $w = W::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($w->status !== 'pending') {
                throw new DomainException('این درخواست در وضعیت قابل تأیید نیست.');
            }
            $amount = Money::irr($approvedAmount ?? $w->requested_amount_irr);
            if ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan(Money::of($w->requested_amount_irr))) {
                throw new DomainException('مبلغ تأییدشده باید مثبت و حداکثر برابر مبلغ درخواستی باشد.');
            }
            $this->assertWithinValue($w->ownershipAccount, $amount, $w->id);

            $state = $this->nav->state();
            [$fee, $net] = $this->feeAndNet($amount);
            $status = $state->cash->isLessThan($net) ? 'awaiting_liquidity' : 'approved';

            $w->forceFill([
                'approved_amount_irr' => (string) $amount,
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            AuditLogger::log('withdrawal.approved', $w, ['status' => 'pending'], ['status' => $status, 'approved_amount_irr' => (string) $amount]);
            if ($w->member_id) {
                Notifier::user($w->member_id, 'withdrawal.approved', 'درخواست برداشت شما تأیید شد',
                    $status === 'awaiting_liquidity' ? 'پرداخت پس از تأمین نقدینگی انجام می‌شود.' : 'پرداخت به‌زودی انجام می‌شود.', $w);
            }

            return $w;
        });
    }

    public function reject(W $request, User $admin, string $reason): W
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل رد الزامی است.');
        }

        return DB::transaction(function () use ($request, $admin, $reason) {
            $w = W::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($w->status, self::OPEN, true)) {
                throw new DomainException('این درخواست در وضعیت قابل رد نیست.');
            }
            $old = $w->status;
            $w->forceFill(['status' => 'rejected', 'admin_note' => $reason, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            AuditLogger::log('withdrawal.rejected', $w, ['status' => $old], ['status' => 'rejected', 'reason' => $reason]);
            if ($w->member_id) {
                Notifier::user($w->member_id, 'withdrawal.rejected', 'درخواست برداشت شما رد شد', 'دلیل: '.$reason, $w);
            }

            return $w;
        });
    }

    public function cancel(W $request, User $actor): W
    {
        return DB::transaction(function () use ($request, $actor) {
            $w = W::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $actor->isAdmin() && $w->member_id !== $actor->id) {
                throw new DomainException('دسترسی غیرمجاز.');
            }
            if (! in_array($w->status, self::OPEN, true)) {
                throw new DomainException('این درخواست قابل لغو نیست.');
            }
            $old = $w->status;
            $w->forceFill(['status' => 'cancelled'])->save();
            AuditLogger::log('withdrawal.cancelled', $w, ['status' => $old], ['status' => 'cancelled']);

            return $w;
        });
    }

    /** Finalise: cancel units at current NAV and record the cash outflow. */
    public function pay(W $request, User $admin, int $cashAccountId): W
    {
        $this->assertAdmin($admin);

        $result = DB::transaction(function () use ($request, $admin, $cashAccountId) {
            $this->nav->lockFund();
            $w = W::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($w->status, ['approved', 'awaiting_liquidity'], true)) {
                throw new DomainException('این درخواست در وضعیت قابل پرداخت نیست.');
            }
            $cash = CashAccount::where('id', $cashAccountId)->where('is_active', true)->first();
            if (! $cash) {
                throw new DomainException('حساب پرداخت نامعتبر است.');
            }

            $amount = Money::irr($w->approved_amount_irr);
            $account = $w->ownershipAccount;
            $state = $this->nav->state();
            $unitsHeld = $this->nav->unitsOf($account);
            $value = Money::irr($unitsHeld->multipliedBy($state->navPerUnit));
            if ($amount->isGreaterThan($value)) {
                throw new DomainException('مبلغ برداشت بیش از ارزش فعلی سبد است (ارزش فعلی: '.Money::format($value).').');
            }
            [$fee, $net] = $this->feeAndNet($amount);

            $cashBal = Money::of($this->ledger->balance(AccountCode::CASH, ['cash_account_id' => $cash->id]));
            if ($cashBal->isLessThan($net)) {
                if ($w->status !== 'awaiting_liquidity') {
                    $w->forceFill(['status' => 'awaiting_liquidity'])->save();
                    AuditLogger::log('withdrawal.awaiting_liquidity', $w, ['status' => 'approved'], ['status' => 'awaiting_liquidity']);
                }

                return ['awaiting', $w];
            }

            $units = $amount->isEqualTo($value)
                ? $unitsHeld
                : $amount->dividedBy($state->navPerUnit, 8, RoundingMode::Up);
            if ($units->isGreaterThan($unitsHeld)) {
                $units = $unitsHeld;
            }

            $capital = $account->isPool() ? AccountCode::POOL_CAPITAL : AccountCode::MEMBER_CAPITAL;
            $desc = 'برداشت '.$account->displayName().' (#'.$w->id.')';
            $lines = [
                ['account' => $capital, 'debit' => $amount, 'ownership_account_id' => $account->id, 'description' => $desc],
                ['account' => AccountCode::CASH, 'credit' => $net, 'cash_account_id' => $cash->id, 'description' => $desc],
            ];
            if ($fee->isPositive()) {
                $lines[] = ['account' => AccountCode::OTHER_INCOME, 'credit' => $fee, 'description' => 'کارمزد '.$desc];
            }
            $tx = $this->ledger->post('withdrawal', $w, now(), $desc, $lines, 'withdrawal:'.$w->id.':pay', $admin);
            $this->units->redeem($account, $units, $state->navPerUnit, $amount, $w, $tx);

            $old = $w->status;
            $w->forceFill([
                'status' => 'paid',
                'fee_irr' => (string) $fee,
                'net_paid_irr' => (string) $net,
                'units_redeemed' => (string) $units->toScale(8),
                'nav_per_unit_irr' => (string) $state->navPerUnit,
                'cash_account_id' => $cash->id,
                'paid_at' => now(),
                'paid_by' => $admin->id,
                'ledger_transaction_id' => $tx->id,
            ])->save();
            $this->nav->recordSnapshot();
            AuditLogger::log('withdrawal.paid', $w, ['status' => $old], ['status' => 'paid', 'net_paid_irr' => (string) $net, 'units_redeemed' => (string) $units]);
            if ($w->member_id) {
                Notifier::user($w->member_id, 'withdrawal.paid', 'برداشت شما پرداخت شد', 'مبلغ خالص '.Money::format($net).' پرداخت شد.', $w);
            }

            return ['paid', $w];
        });

        if ($result[0] === 'awaiting') {
            throw new DomainException('نقدینگی حساب انتخابی کافی نیست؛ درخواست در وضعیت «در انتظار تأمین نقدینگی» قرار گرفت.');
        }

        return $result[1];
    }

    /** @return array{0: BigDecimal, 1: BigDecimal} [fee, net] */
    public function feeAndNet(BigDecimal $amount): array
    {
        $pct = Money::of(FundSetting::current()->withdrawal_fee_percent ?: 0);
        $fee = Money::irr($amount->multipliedBy($pct)->dividedBy(100, 2, RoundingMode::HalfUp));

        return [$fee, $amount->minus($fee)];
    }

    private function assertWithinValue(OwnershipAccount $account, BigDecimal $amount, ?int $excludeId): void
    {
        $value = $this->nav->valueOf($account);
        $open = Money::of(W::where('ownership_account_id', $account->id)
            ->whereIn('status', self::OPEN)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum(DB::raw('COALESCE(approved_amount_irr, requested_amount_irr)')) ?: 0);
        if ($amount->plus($open)->isGreaterThan($value)) {
            throw new DomainException('مبلغ برداشت (به‌همراه درخواست‌های باز) بیش از ارزش فعلی سبد است. ارزش فعلی: '.Money::format($value));
        }
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new DomainException('فقط مدیر مجاز به این عملیات است.');
        }
    }
}
