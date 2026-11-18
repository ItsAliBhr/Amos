<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyCommitment extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'unpaid' => 'پرداخت‌نشده',
        'partial' => 'ناقص',
        'complete' => 'کامل',
        'surplus' => 'مازاد',
        'exempt' => 'معاف',
        'upcoming' => 'آینده',
    ];

    protected function casts(): array
    {
        return [
            'commitment_amount_irr' => 'decimal:2',
            'exemption_amount_irr' => 'decimal:2',
        ];
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id')->withTrashed();
    }
}
