<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'approved_at' => 'datetime'];
    }

    public const TYPES = [
        'contribution' => 'واریزی',
        'contribution_reversal' => 'برگشت واریزی',
        'investment_buy' => 'خرید دارایی',
        'investment_sell' => 'فروش دارایی',
        'investment_expense' => 'هزینه',
        'investment_income' => 'درآمد',
        'cash_transfer' => 'انتقال وجه',
        'investment_reversal' => 'برگشت عملیات سرمایه‌گذاری',
        'withdrawal' => 'برداشت',
        'opening_balance' => 'موجودی افتتاحیه',
    ];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
