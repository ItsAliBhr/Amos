<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Idea extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'submitted' => 'ارسال‌شده',
        'under_review' => 'در حال بررسی',
        'needs_information' => 'نیازمند اطلاعات',
        'approved' => 'تصویب‌شده',
        'rejected' => 'ردشده',
        'on_hold' => 'معلق',
        'executed' => 'اجراشده',
        'cancelled' => 'لغوشده',
    ];

    public const OPINIONS = [
        'agree' => 'موافق',
        'disagree' => 'مخالف',
        'abstain' => 'ممتنع',
    ];

    public const PERIODS = [
        'monthly' => 'ماهانه',
        'yearly' => 'سالانه',
        'total' => 'کل دوره',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'required_capital_irr' => 'decimal:2',
            'expected_return_min' => 'decimal:2',
            'expected_return_max' => 'decimal:2',
            'decision_at' => 'datetime',
        ];
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by')->withTrashed();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decision_by')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function reviews()
    {
        return $this->hasMany(IdeaReview::class);
    }

    public function reviewHistories()
    {
        return $this->hasMany(IdeaReviewHistory::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(IdeaStatusHistory::class);
    }

    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }

    public function investments()
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function scopeVisibleTo($q, User $user)
    {
        if ($user->isAdmin()) {
            return $q->where(fn ($w) => $w->where('status', '!=', 'draft')->orWhere('proposed_by', $user->id));
        }

        return $q->where(fn ($w) => $w->where('status', '!=', 'draft')->orWhere('proposed_by', $user->id));
    }
}
