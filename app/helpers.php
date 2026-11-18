<?php

use App\Support\Jalali;
use App\Support\Money;

if (! function_exists('money')) {
    function money($irr, bool $withUnit = true): string
    {
        return Money::format($irr, $withUnit);
    }
}

if (! function_exists('num')) {
    function num($value, int $decimals = 0): string
    {
        return Money::formatNumber($value, $decimals);
    }
}

if (! function_exists('jdate')) {
    function jdate($date, bool $withTime = false): string
    {
        return Jalali::format($date, $withTime);
    }
}

if (! function_exists('money_input')) {
    /** IRR -> value shown inside a money input (display currency, grouped). */
    function money_input($irr): string
    {
        if ($irr === null || $irr === '') {
            return '';
        }

        return Money::groupDigits(Money::toDisplayNumber($irr));
    }
}

if (! function_exists('currency_label')) {
    function currency_label(): string
    {
        return Money::displayUnitLabel();
    }
}

if (! function_exists('amos_installed')) {
    function amos_installed(): bool
    {
        $forced = config('amos.installed');
        if ($forced !== null && $forced !== '') {
            return filter_var($forced, FILTER_VALIDATE_BOOLEAN);
        }

        return is_file(config('amos.lock_file'));
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status, ?string $label = null): string
    {
        $map = [
            'draft' => ['پیش‌نویس', 'secondary'],
            'pending' => ['در انتظار تأیید', 'warning'],
            'approved' => ['تأییدشده', 'success'],
            'rejected' => ['ردشده', 'danger'],
            'reversed' => ['برگشت‌خورده', 'dark'],
            'paid' => ['پرداخت‌شده', 'success'],
            'awaiting_liquidity' => ['در انتظار نقدینگی', 'info'],
            'cancelled' => ['لغوشده', 'secondary'],
            'submitted' => ['ارسال‌شده', 'primary'],
            'under_review' => ['در حال بررسی', 'warning'],
            'needs_information' => ['نیازمند اطلاعات', 'info'],
            'on_hold' => ['معلق', 'secondary'],
            'executed' => ['اجراشده', 'success'],
            'active' => ['فعال', 'success'],
            'inactive' => ['غیرفعال', 'secondary'],
            'suspended' => ['تعلیق', 'danger'],
            'unpaid' => ['پرداخت‌نشده', 'danger'],
            'partial' => ['ناقص', 'warning'],
            'complete' => ['کامل', 'success'],
            'surplus' => ['مازاد', 'primary'],
            'exempt' => ['معاف', 'secondary'],
            'upcoming' => ['آینده', 'light'],
            'posted' => ['ثبت‌شده', 'success'],
            'agree' => ['موافق', 'success'],
            'disagree' => ['مخالف', 'danger'],
            'abstain' => ['ممتنع', 'secondary'],
        ];
        [$text, $color] = $map[$status] ?? [$status ?? '—', 'secondary'];
        $text = $label ?? $text;
        $textClass = in_array($color, ['warning', 'light', 'info'], true) ? ' text-dark' : '';

        return '<span class="badge rounded-pill text-bg-'.$color.$textClass.'">'.e($text).'</span>';
    }
}

if (! function_exists('idem_key')) {
    function idem_key(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}

if (! function_exists('js_json')) {
    /** JSON safe for embedding inside <script type="application/json">. */
    function js_json($data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}
