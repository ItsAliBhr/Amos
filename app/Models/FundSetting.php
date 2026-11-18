<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundSetting extends Model
{
    protected $guarded = ['id'];

    protected static ?FundSetting $cached = null;

    protected function casts(): array
    {
        return [
            'initial_unit_price_irr' => 'decimal:8',
            'default_monthly_commitment_irr' => 'decimal:2',
            'monthly_contribution_cap_irr' => 'decimal:2',
            'min_contribution_irr' => 'decimal:2',
            'withdrawal_fee_percent' => 'decimal:4',
            'withdrawal_min_irr' => 'decimal:2',
            'allow_partial_payment' => 'boolean',
            'allow_early_payment' => 'boolean',
            'allow_late_payment' => 'boolean',
            'withdrawal_enabled' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public const CAP_MODES = [
        'require_override' => 'نیازمند تأیید ویژه مدیر',
        'block' => 'مسدود کردن ثبت',
        'warn' => 'فقط هشدار',
    ];

    public const CAP_SCOPES = [
        'commitment_month' => 'بر اساس ماه تعهد',
        'payment_month' => 'بر اساس ماه پرداخت',
    ];

    public const VISIBILITY = [
        'full' => 'کامل (ارزش کل و ترکیب دارایی‌ها)',
        'summary' => 'خلاصه (فقط ارزش هر واحد)',
        'none' => 'عدم نمایش اطلاعات کل صندوق',
    ];

    public static function current(): self
    {
        if (static::$cached && static::$cached->exists) {
            return static::$cached;
        }

        return static::$cached = static::query()->first() ?? static::query()->create([]);
    }

    public static function flushCache(): void
    {
        static::$cached = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
    }
}
