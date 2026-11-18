<?php

namespace App\Domain\Members;

use App\Models\Contribution;
use App\Models\ContributionType;
use App\Models\MonthlyCommitment;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Monthly commitment tracking. Only approved "monthly" contributions count; donations never count. */
class CommitmentService
{
    /** @return array{year:int, month:int, label:string, commitment:string, exemption:string, due:string, paid:string, status:string, note:?string} */
    public function statusFor(User $member, int $year, int $month): array
    {
        $row = MonthlyCommitment::where('member_id', $member->id)->where('year', $year)->where('month', $month)->first();
        $commitment = Money::of($row?->commitment_amount_irr ?? $member->effectiveMonthlyCommitment());
        $exemption = Money::of($row?->exemption_amount_irr ?? 0);
        $due = $commitment->minus($exemption);
        if ($due->isNegative()) {
            $due = Money::zero();
        }

        $paid = Money::of(Contribution::query()
            ->where('member_id', $member->id)
            ->where('status', Contribution::STATUS_APPROVED)
            ->where('commitment_year', $year)->where('commitment_month', $month)
            ->whereHas('type', fn ($t) => $t->where('counts_toward_commitment', true))
            ->sum('amount_irr') ?: 0);

        [$cy, $cm] = Jalali::currentYearMonth();
        $isFuture = ($year * 12 + $month) > ($cy * 12 + $cm);

        if ($due->isZero()) {
            $status = 'exempt';
        } elseif ($paid->isZero()) {
            $status = $isFuture ? 'upcoming' : 'unpaid';
        } elseif ($paid->isLessThan($due)) {
            $status = 'partial';
        } elseif ($paid->isEqualTo($due)) {
            $status = 'complete';
        } else {
            $status = 'surplus';
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => Jalali::monthLabel($year, $month),
            'commitment' => (string) $commitment,
            'exemption' => (string) $exemption,
            'due' => (string) $due,
            'paid' => (string) $paid,
            'status' => $status,
            'note' => $row?->note,
        ];
    }

    /** Commitment history from join date (max $limit months) up to the current month, newest first. */
    public function history(User $member, int $limit = 24): array
    {
        [$cy, $cm] = Jalali::currentYearMonth();
        [$sy, $sm] = $member->joined_at ? Jalali::fromDate($member->joined_at) : Jalali::fromDate($member->created_at);
        $out = [];
        [$y, $m] = [$cy, $cm];
        for ($i = 0; $i < $limit; $i++) {
            if ($y * 12 + $m < $sy * 12 + $sm) {
                break;
            }
            $out[] = $this->statusFor($member, $y, $m);
            [$y, $m] = Jalali::addMonths($y, $m, -1);
        }

        return $out;
    }

    public function setOverride(User $member, int $year, int $month, string $commitmentIrr, string $exemptionIrr, ?string $note, User $admin): MonthlyCommitment
    {
        return DB::transaction(function () use ($member, $year, $month, $commitmentIrr, $exemptionIrr, $note, $admin) {
            $row = MonthlyCommitment::firstOrNew(['member_id' => $member->id, 'year' => $year, 'month' => $month]);
            $old = $row->exists ? $row->only(['commitment_amount_irr', 'exemption_amount_irr', 'note']) : null;
            $row->fill([
                'commitment_amount_irr' => $commitmentIrr,
                'exemption_amount_irr' => $exemptionIrr,
                'note' => $note,
                'updated_by' => $admin->id,
            ]);
            $row->status = $this->statusFor($member, $year, $month)['status'];
            $row->save();
            $row->status = $this->statusFor($member, $year, $month)['status'];
            $row->save();
            \App\Services\AuditLogger::log('commitment.updated', $row, $old, $row->only(['commitment_amount_irr', 'exemption_amount_irr', 'note']));

            return $row;
        });
    }

    public static function monthlyTypeId(): ?int
    {
        return ContributionType::where('code', ContributionType::MONTHLY)->value('id');
    }
}
