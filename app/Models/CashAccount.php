<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['account_number_encrypted'];

    public const TYPES = [
        'bank' => 'حساب بانکی',
        'cash' => 'صندوق نقدی',
        'broker' => 'حساب کارگزاری',
        'exchange' => 'حساب صرافی/پلتفرم',
        'other' => 'سایر',
    ];

    protected function casts(): array
    {
        return [
            'account_number_encrypted' => 'encrypted',
            'opening_balance_irr' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function maskedNumber(): ?string
    {
        $n = $this->account_number_encrypted;
        if (! $n) {
            return null;
        }

        return strlen($n) > 4 ? str_repeat('•', 6).substr($n, -4) : $n;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
