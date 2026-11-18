<?php

namespace App\Domain\Accounting;

/** Logical chart of accounts used by the general ledger. */
final class AccountCode
{
    public const CASH = '1100';               // نقدینگی (per cash_account_id)
    public const INVESTMENT = '1200';         // دارایی سرمایه‌گذاری - بهای تمام‌شده (per asset_id, with quantity)
    public const RECEIVABLE = '1300';         // مطالبات
    public const PAYABLE = '2100';            // بدهی‌ها
    public const MEMBER_CAPITAL = '3100';     // سرمایه عضو (per ownership_account_id)
    public const POOL_CAPITAL = '3200';       // سرمایه Pool (per ownership_account_id)
    public const REALIZED_PNL = '4100';       // سود/زیان تحقق‌یافته
    public const UNREALIZED_PNL = '4200';     // سود/زیان ارزش‌گذاری (memo; computed from valuations)
    public const OTHER_INCOME = '4300';       // سایر درآمدها
    public const EXPENSE = '5100';            // هزینه و کارمزد

    public const LABELS = [
        self::CASH => 'نقدینگی',
        self::INVESTMENT => 'دارایی سرمایه‌گذاری',
        self::RECEIVABLE => 'مطالبات',
        self::PAYABLE => 'بدهی‌ها',
        self::MEMBER_CAPITAL => 'سرمایه عضو',
        self::POOL_CAPITAL => 'سرمایه Pool',
        self::REALIZED_PNL => 'سود/زیان تحقق‌یافته',
        self::UNREALIZED_PNL => 'سود/زیان ارزش‌گذاری',
        self::OTHER_INCOME => 'سایر درآمدها',
        self::EXPENSE => 'هزینه و کارمزد',
    ];

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }
}
