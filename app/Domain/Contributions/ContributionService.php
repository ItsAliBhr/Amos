<?php

namespace App\Domain\Contributions;

use App\Domain\Accounting\AccountCode;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\NavService;
use App\Domain\Accounting\UnitService;
use App\Domain\DomainException;
use App\Models\CashAccount;
use App\Models\Contribution;
use App\Models\ContributionType;
use App\Models\FundSetting;
use App\Models\OwnershipAccount;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Jalali;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class ContributionService
{
    public function __construct(
        private LedgerService $ledger,
        private NavService $nav,
        private UnitService $units,
    ) {
    }

    /**
     * @param  array{member_id?:int|null, type_code:string, target?:string|null, amount_irr:BigDecimal, contribution_date:\DateTimeInterface, commitment_year?:int|null, commitment_month?:int|null, destination_account_id?:int|null, payment_method?:string|null, tracking_code?:string|null, description?:string|null, receipt_attachment_id?:int|null, idempotency_key?:string|null, as_draft?:bool}  $data
     */
    public function create(array $data, User $actor): Contribution
    {
        if (! empty($data['idempotency_key']) && ($existing = Contribution::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing; // double submit: no duplicate effect
        }

        $type = ContributionType::where('code', $data['type_code'])->where('is_active', true)->first();
        if (! $type) {
            throw new DomainException('نوع واریزی نامعتبر است.');
        }
        if ($type->admin_only && ! $actor->isAdmin()) {
            throw new DomainException('ثبت این نوع واریزی فقط توسط مدیر مجاز است.');
        }

        $amount = Money::irr($data['amount_irr']);
        if ($amount->isLessThanOrEqualTo(0)) {
            throw new DomainException('مبلغ واریزی باید مثبت باشد.');
        }
        $settings = FundSetting::current();
        if ($amount->isLessThan(Money::of($settings->min_contribution_irr))) {
            throw new DomainException('مبلغ واریزی کمتر از حداقل مجاز است ('.Money::format($settings->min_contribution_irr).').');
        }

        // Members can only submit for themselves; admins may submit on behalf of a member.
        $memberId = $actor->isAdmin() ? ($data['member_id'] ?? null) : $actor->id;
        $member = $memberId ? User::find($memberId) : null;

        $ownership = $this->resolveOwnership($type, $member, $data['target'] ?? null);
        if (! $ownership->isPool() && ! $member) {
            throw new DomainException('عضو پرداخت‌کننده مشخص نشده است.');
        }

        [$cy, $cm] = [$data['commitment_year'] ?? null, $data['commitment_month'] ?? null];
        if ($type->code === ContributionType::MONTHLY) {
            if (! $cy || ! $cm) {
                [$cy, $cm] = Jalali::fromDate($data['contribution_date']);
            }
            $this->assertTimingAllowed($settings, (int) $cy, (int) $cm);
        } elseif ($type->counts_toward_cap && (! $cy || ! $cm)) {
            [$cy, $cm] = Jalali::fromDate($data['contribution_date']);
        }

        if (! empty($data['destination_account_id']) && ! CashAccount::where('id', $data['destination_account_id'])->where('is_active', true)->exists()) {
            throw new DomainException('حساب مقصد نامعتبر است.');
        }

        $contribution = new Contribution([
            'member_id' => $member?->id,
            'type_id' => $type->id,
            'ownership_account_id' => $ownership->id,
            'amount_irr' => (string) $amount,
            'contribution_date' => $data['contribution_date'],
            'commitment_year' => $cy,
            'commitment_month' => $cm,
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'tracking_code' => $data['tracking_code'] ?? null,
            'receipt_attachment_id' => $data['receipt_attachment_id'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => ! empty($data['as_draft']) ? Contribution::STATUS_DRAFT : Contribution::STATUS_PENDING,
            'submitted_by' => $actor->id,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        $contribution->exceeds_cap = $this->exceedsCap($contribution, $type);
        if ($contribution->exceeds_cap && $settings->cap_mode === 'block') {
            throw new DomainException('این واریزی از سقف ماهانه واریزی اختصاصی ('.Money::format($settings->monthly_contribution_cap_irr).') عبور می‌کند.');
        }

        DB::transaction(function () use ($contribution) {
            $contribution->save();
            AuditLogger::log('contribution.created', $contribution, null, $contribution->only(['member_id', 'type_id', 'amount_irr', 'status', 'ownership_account_id']));
        });

        if ($contribution->status === Contribution::STATUS_PENDING) {
            $this->notifyNew($contribution, $actor);
        }

        return $contribution;
    }

    public function submitDraft(Contribution $c, User $actor): Contribution
    {
        if ($c->status !== Contribution::STATUS_DRAFT) {
            throw new DomainException('فقط پیش‌نویس قابل ارسال است.');
        }
        $c->exceeds_cap = $this->exceedsCap($c, $c->type);
        if ($c->exceeds_cap && FundSetting::current()->cap_mode === 'block') {
            throw new DomainException('این واریزی از سقف ماهانه عبور می‌کند.');
        }
        $c->status = Contribution::STATUS_PENDING;
        $c->save();
        AuditLogger::log('contribution.submitted', $c);
        $this->notifyNew($c, $actor);

        return $c;
    }

    public function approve(Contribution $contribution, User $admin, bool $capOverride = false, ?int $destinationAccountId = null): Contribution
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($contribution, $admin, $capOverride, $destinationAccountId) {
            $this->nav->lockFund();
            /** @var Contribution $c */
            $c = Contribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();
            if ($c->status !== Contribution::STATUS_PENDING) {
                throw new DomainException('این واریزی در وضعیت قابل تأیید نیست (احتمالاً قبلاً بررسی شده است).');
            }

            $destId = $destinationAccountId ?: $c->destination_account_id;
            $dest = $destId ? CashAccount::where('id', $destId)->where('is_active', true)->first() : null;
            if (! $dest) {
                throw new DomainException('برای تأیید، حساب مقصد فعال باید مشخص باشد.');
            }

            $type = $c->type;
            $settings = FundSetting::current();
            $exceeds = $this->exceedsCap($c, $type);
            if ($exceeds && $settings->cap_mode === 'block') {
                throw new DomainException('این واریزی از سقف ماهانه عبور می‌کند و طبق تنظیمات قابل تأیید نیست.');
            }
            if ($exceeds && $settings->cap_mode === 'require_override' && ! $capOverride) {
                throw new DomainException('این واریزی از سقف ماهانه عبور می‌کند؛ برای تأیید، گزینه «تأیید ویژه عبور از سقف» را انتخاب کنید.');
            }

            $ownership = $c->ownershipAccount;
            $state = $this->nav->state();
            if ($state->totalUnits->isGreaterThan(0) && $state->nav->isLessThanOrEqualTo(0)) {
                throw new DomainException('ارزش خالص صندوق صفر یا منفی است؛ صدور واحد امکان‌پذیر نیست.');
            }
            $navPerUnit = $state->navPerUnit;
            $amount = Money::irr($c->amount_irr);

            $capitalAccount = $ownership->isPool() ? AccountCode::POOL_CAPITAL : AccountCode::MEMBER_CAPITAL;
            $desc = 'واریزی '.$type->name.' - '.$ownership->displayName().' (#'.$c->id.')';
            $tx = $this->ledger->post('contribution', $c, $c->contribution_date, $desc, [
                ['account' => AccountCode::CASH, 'debit' => $amount, 'cash_account_id' => $dest->id, 'description' => $desc],
                ['account' => $capitalAccount, 'credit' => $amount, 'ownership_account_id' => $ownership->id, 'description' => $desc],
            ], 'contribution:'.$c->id.':approve', $admin);

            $unitRow = $this->units->issue($ownership, $amount, $navPerUnit, $c, $tx);

            $c->forceFill([
                'status' => Contribution::STATUS_APPROVED,
                'destination_account_id' => $dest->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'ledger_transaction_id' => $tx->id,
                'units_issued' => $unitRow->units,
                'nav_per_unit_irr' => (string) $navPerUnit,
                'exceeds_cap' => $exceeds,
                'cap_override' => $exceeds && $capOverride,
            ])->save();

            $this->nav->recordSnapshot();
            AuditLogger::log('contribution.approved', $c, ['status' => 'pending'], [
                'status' => 'approved', 'units_issued' => (string) $unitRow->units, 'nav_per_unit' => (string) $navPerUnit,
                'ledger_transaction_id' => $tx->id, 'cap_override' => $c->cap_override,
            ]);
            if ($c->member_id) {
                Notifier::user($c->member_id, 'contribution.approved', 'واریزی شما تأیید شد', 'مبلغ '.Money::format($c->amount_irr).' تأیید و ثبت شد.', $c);
            }

            return $c;
        });
    }

    public function reject(Contribution $contribution, User $admin, string $reason): Contribution
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل رد الزامی است.');
        }

        return DB::transaction(function () use ($contribution, $admin, $reason) {
            $c = Contribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();
            if (! in_array($c->status, [Contribution::STATUS_PENDING, Contribution::STATUS_DRAFT], true)) {
                throw new DomainException('این واریزی در وضعیت قابل رد نیست.');
            }
            $old = $c->status;
            $c->forceFill([
                'status' => Contribution::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ])->save();
            AuditLogger::log('contribution.rejected', $c, ['status' => $old], ['status' => 'rejected', 'reason' => $reason]);
            if ($c->member_id) {
                Notifier::user($c->member_id, 'contribution.rejected', 'واریزی شما رد شد', 'دلیل: '.$reason, $c);
            }

            return $c;
        });
    }

    /** Correct an approved contribution with a reversal document (no edit/delete of posted data). */
    public function reverse(Contribution $contribution, User $admin, string $reason): Contribution
    {
        $this->assertAdmin($admin);
        if (trim($reason) === '') {
            throw new DomainException('ثبت دلیل برگشت الزامی است.');
        }

        return DB::transaction(function () use ($contribution, $admin, $reason) {
            $this->nav->lockFund();
            $c = Contribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();
            if ($c->status !== Contribution::STATUS_APPROVED) {
                throw new DomainException('فقط واریزی تأییدشده قابل برگشت است.');
            }
            $units = Money::of($c->units_issued);
            if ($this->nav->unitsOf($c->ownership_account_id)->isLessThan($units)) {
                throw new DomainException('واحدهای کافی در سبد برای ابطال وجود ندارد (احتمالاً برداشت انجام شده است).');
            }
            $cashBal = Money::of($this->ledger->balance(AccountCode::CASH, ['cash_account_id' => $c->destination_account_id]));
            if ($cashBal->isLessThan(Money::of($c->amount_irr))) {
                throw new DomainException('موجودی حساب مقصد برای برگشت این واریزی کافی نیست.');
            }

            $tx = $this->ledger->reverse($c->ledgerTransaction, 'contribution_reversal', 'برگشت واریزی #'.$c->id.' - '.$reason, $admin);
            $this->units->record($c->ownershipAccount, $units->negated(), Money::of($c->nav_per_unit_irr), Money::of($c->amount_irr)->negated(), $c, $tx);

            $c->forceFill([
                'status' => Contribution::STATUS_REVERSED,
                'reversed_by' => $admin->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversal_ledger_transaction_id' => $tx->id,
            ])->save();

            $this->nav->recordSnapshot();
            AuditLogger::log('contribution.reversed', $c, ['status' => 'approved'], ['status' => 'reversed', 'reason' => $reason, 'ledger_transaction_id' => $tx->id]);
            if ($c->member_id) {
                Notifier::user($c->member_id, 'contribution.reversed', 'واریزی شما برگشت خورد', 'دلیل: '.$reason, $c);
            }

            return $c;
        });
    }

    /** Members may withdraw their own draft/pending submissions (no financial effect yet). */
    public function cancel(Contribution $c, User $actor): void
    {
        if (! in_array($c->status, [Contribution::STATUS_DRAFT, Contribution::STATUS_PENDING], true)) {
            throw new DomainException('فقط واریزی پیش‌نویس یا در انتظار قابل لغو است.');
        }
        if (! $actor->isAdmin() && $c->submitted_by !== $actor->id) {
            throw new DomainException('دسترسی غیرمجاز.');
        }
        DB::transaction(function () use ($c) {
            AuditLogger::log('contribution.cancelled', $c, $c->only(['status', 'amount_irr', 'member_id']), null);
            $c->forceFill(['status' => Contribution::STATUS_REJECTED, 'rejection_reason' => 'لغو توسط ثبت‌کننده'])->save();
        });
    }

    public function resolveOwnership(ContributionType $type, ?User $member, ?string $target): OwnershipAccount
    {
        if ($type->ownership_type === OwnershipAccount::TYPE_POOL) {
            return OwnershipAccount::pool();
        }
        if ($type->code === ContributionType::ADJUSTMENT && $target === 'pool') {
            return OwnershipAccount::pool();
        }
        if (! $member) {
            throw new DomainException('عضو پرداخت‌کننده مشخص نشده است.');
        }

        return OwnershipAccount::forMember($member);
    }

    /** Sum of member-owned (monthly + extra) contributions counted toward the monthly cap. */
    public function capUsage(int $memberId, int $year, int $month, ?int $excludeId = null): BigDecimal
    {
        $settings = FundSetting::current();
        $q = Contribution::query()
            ->where('member_id', $memberId)
            ->whereIn('status', [Contribution::STATUS_PENDING, Contribution::STATUS_APPROVED])
            ->whereHas('type', fn ($t) => $t->where('counts_toward_cap', true))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        if ($settings->cap_scope === 'payment_month') {
            [$start, $end] = Jalali::monthRange($year, $month);
            $q->whereBetween('contribution_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        } else {
            $q->where('commitment_year', $year)->where('commitment_month', $month);
        }

        return Money::of($q->sum('amount_irr') ?: 0);
    }

    public function exceedsCap(Contribution $c, ContributionType $type): bool
    {
        $cap = FundSetting::current()->monthly_contribution_cap_irr;
        if (! $type->counts_toward_cap || $cap === null || ! $c->member_id || Money::of($cap)->isZero()) {
            return false;
        }
        if (FundSetting::current()->cap_scope === 'payment_month') {
            [$y, $m] = Jalali::fromDate($c->contribution_date);
        } else {
            [$y, $m] = [(int) $c->commitment_year, (int) $c->commitment_month];
        }
        $used = $this->capUsage($c->member_id, $y, $m, $c->id);

        return $used->plus(Money::of($c->amount_irr))->isGreaterThan(Money::of($cap));
    }

    private function assertTimingAllowed(FundSetting $settings, int $y, int $m): void
    {
        [$cy, $cm] = Jalali::currentYearMonth();
        $target = $y * 12 + $m;
        $now = $cy * 12 + $cm;
        if ($target > $now && ! $settings->allow_early_payment) {
            throw new DomainException('پرداخت زودهنگام تعهد ماه‌های آینده مجاز نیست.');
        }
        if ($target < $now && ! $settings->allow_late_payment) {
            throw new DomainException('پرداخت با تأخیر برای ماه‌های گذشته مجاز نیست.');
        }
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new DomainException('فقط مدیر مجاز به این عملیات است.');
        }
    }

    private function notifyNew(Contribution $c, User $actor): void
    {
        Notifier::admins('contribution.new', 'واریزی جدید در انتظار تأیید', ($c->member?->name ?? 'Pool').' - '.Money::format($c->amount_irr), $c, $actor->id);
    }
}
