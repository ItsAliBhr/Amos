<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentTransaction extends Model
{
    public const TYPE_BUY = 'buy';
    public const TYPE_SELL = 'sell';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_INCOME = 'income';
    public const TYPE_TRANSFER = 'transfer';

    public const TYPES = [
        self::TYPE_BUY => 'خرید',
        self::TYPE_SELL => 'فروش',
        self::TYPE_EXPENSE => 'هزینه',
        self::TYPE_INCOME => 'درآمد',
        self::TYPE_TRANSFER => 'انتقال وجه بین حساب‌ها',
    ];

    public const EXPENSE_CATEGORIES = [
        'fee' => 'کارمزد',
        'custody' => 'نگهداری',
        'transfer' => 'انتقال',
        'other' => 'سایر',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'unit_price_irr' => 'decimal:8',
            'gross_amount_irr' => 'decimal:2',
            'fee_amount_irr' => 'decimal:2',
            'net_amount_irr' => 'decimal:2',
            'cost_basis_irr' => 'decimal:2',
            'realized_pnl_irr' => 'decimal:2',
            'transaction_date' => 'date',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function toCashAccount()
    {
        return $this->belongsTo(CashAccount::class, 'to_cash_account_id');
    }

    public function attachment()
    {
        return $this->belongsTo(Attachment::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }
}
