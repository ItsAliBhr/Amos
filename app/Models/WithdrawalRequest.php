<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    public const STATUSES = [
        'pending' => 'در انتظار بررسی',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
        'awaiting_liquidity' => 'در انتظار تأمین نقدینگی',
        'paid' => 'پرداخت‌شده',
        'cancelled' => 'لغوشده',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requested_amount_irr' => 'decimal:2',
            'approved_amount_irr' => 'decimal:2',
            'fee_irr' => 'decimal:2',
            'net_paid_irr' => 'decimal:2',
            'units_redeemed' => 'decimal:8',
            'nav_per_unit_irr' => 'decimal:8',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }

    public function ownershipAccount()
    {
        return $this->belongsTo(OwnershipAccount::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }
}
