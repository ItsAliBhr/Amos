<?php

namespace App\Domain\Ideas;

use App\Domain\DomainException;
use App\Models\Idea;
use App\Models\IdeaReview;
use App\Models\IdeaReviewHistory;
use App\Models\IdeaStatusHistory;
use App\Models\InvestmentTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

class IdeaService
{
    public const EDITABLE = ['draft', 'needs_information'];
    public const REVIEWABLE = ['submitted', 'under_review', 'needs_information'];
    public const DECISIONS_REQUIRING_NOTE = ['approved', 'rejected', 'on_hold', 'cancelled', 'needs_information'];

    public function create(array $data, User $user): Idea
    {
        return DB::transaction(function () use ($data, $user) {
            $status = ! empty($data['as_draft']) ? 'draft' : 'submitted';
            $idea = Idea::create(array_merge($this->fields($data), [
                'status' => $status,
                'proposed_by' => $user->id,
            ]));
            $this->history($idea, null, $status, null, $user);
            AuditLogger::log('idea.created', $idea, null, $idea->only(['title', 'status']));
            if ($status === 'submitted') {
                Notifier::allActive('idea.new', 'ایده سرمایه‌گذاری جدید', $idea->title, $idea, $user->id);
            }

            return $idea;
        });
    }

    public function update(Idea $idea, array $data, User $user): Idea
    {
        if ($idea->proposed_by !== $user->id && ! $user->isAdmin()) {
            throw new DomainException('فقط پیشنهاددهنده می‌تواند ایده را ویرایش کند.');
        }
        if (! in_array($idea->status, self::EDITABLE, true)) {
            throw new DomainException('ایده در این وضعیت قابل ویرایش نیست.');
        }

        return DB::transaction(function () use ($idea, $data, $user) {
            $idea->fill($this->fields($data));
            $idea->save();
            [$old, $new] = AuditLogger::changes($idea);
            AuditLogger::log('idea.updated', $idea, $old, $new);
            if (empty($data['as_draft']) && in_array($idea->status, self::EDITABLE, true)) {
                $this->transition($idea, 'submitted', null, $user);
                Notifier::admins('idea.submitted', 'ایده برای بررسی ارسال شد', $idea->title, $idea, $user->id);
            }

            return $idea;
        });
    }

    public function review(Idea $idea, User $member, string $opinion, ?string $comment): IdeaReview
    {
        if (! array_key_exists($opinion, Idea::OPINIONS)) {
            throw new DomainException('نظر نامعتبر است.');
        }
        if (! in_array($idea->status, self::REVIEWABLE, true)) {
            throw new DomainException('ثبت نظر برای ایده در این وضعیت امکان‌پذیر نیست.');
        }

        return DB::transaction(function () use ($idea, $member, $opinion, $comment) {
            $review = IdeaReview::firstOrNew(['idea_id' => $idea->id, 'member_id' => $member->id]);
            $old = $review->exists ? $review->only(['opinion', 'comment']) : null;
            $review->fill(['opinion' => $opinion, 'comment' => $comment])->save();
            IdeaReviewHistory::create([
                'idea_id' => $idea->id, 'member_id' => $member->id, 'opinion' => $opinion, 'comment' => $comment, 'created_at' => now(),
            ]);
            AuditLogger::log('idea.reviewed', $idea, $old, ['opinion' => $opinion, 'comment' => $comment]);
            if ($idea->proposed_by !== $member->id) {
                Notifier::user($idea->proposed_by, 'idea.review', 'نظر جدید روی ایده شما', $member->name.': '.Idea::OPINIONS[$opinion], $idea);
            }

            return $review;
        });
    }

    public function decide(Idea $idea, string $status, ?string $note, User $admin): Idea
    {
        if (! $admin->isAdmin()) {
            throw new DomainException('تعیین وضعیت نهایی ایده فقط توسط مدیر مجاز است.');
        }
        if (! array_key_exists($status, Idea::STATUSES) || in_array($status, ['draft', 'executed'], true)) {
            throw new DomainException('وضعیت انتخابی نامعتبر است.');
        }
        if (in_array($status, self::DECISIONS_REQUIRING_NOTE, true) && trim((string) $note) === '') {
            throw new DomainException('ثبت دلیل تصمیم الزامی است.');
        }
        if ($idea->status === 'draft') {
            throw new DomainException('ایده پیش‌نویس هنوز ارسال نشده است.');
        }

        return DB::transaction(function () use ($idea, $status, $note, $admin) {
            $this->transition($idea, $status, $note, $admin);
            if (in_array($status, ['approved', 'rejected', 'on_hold', 'cancelled'], true)) {
                $idea->forceFill(['decision_by' => $admin->id, 'decision_at' => now(), 'decision_note' => $note])->save();
            }
            Notifier::allActive('idea.status', 'تغییر وضعیت ایده', $idea->title.': '.Idea::STATUSES[$status], $idea, $admin->id);

            return $idea;
        });
    }

    public function withdraw(Idea $idea, User $user): Idea
    {
        if ($idea->proposed_by !== $user->id) {
            throw new DomainException('فقط پیشنهاددهنده می‌تواند ایده را لغو کند.');
        }
        if (in_array($idea->status, ['approved', 'executed', 'rejected', 'cancelled'], true)) {
            throw new DomainException('ایده در این وضعیت قابل لغو نیست.');
        }
        DB::transaction(fn () => $this->transition($idea, 'cancelled', 'لغو توسط پیشنهاددهنده', $user));

        return $idea;
    }

    public function markExecuted(Idea $idea, InvestmentTransaction $tx, User $admin): void
    {
        $idea->forceFill(['executed_investment_id' => $tx->id])->save();
        $this->transition($idea, 'executed', 'اجرا با عملیات سرمایه‌گذاری #'.$tx->id, $admin);
    }

    private function transition(Idea $idea, string $to, ?string $note, User $user): void
    {
        $from = $idea->status;
        if ($from === $to) {
            return;
        }
        $idea->forceFill(['status' => $to])->save();
        $this->history($idea, $from, $to, $note, $user);
        AuditLogger::log('idea.status_changed', $idea, ['status' => $from], ['status' => $to, 'note' => $note]);
    }

    private function history(Idea $idea, ?string $from, string $to, ?string $note, User $user): void
    {
        IdeaStatusHistory::create([
            'idea_id' => $idea->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'changed_by' => $user->id, 'created_at' => now(),
        ]);
    }

    private function fields(array $data): array
    {
        return collect($data)->only([
            'title', 'category_id', 'description', 'required_capital_irr', 'time_horizon', 'expected_return_min',
            'expected_return_max', 'expected_return_period', 'risk_description', 'assumptions', 'attachment_id',
        ])->all();
    }
}
