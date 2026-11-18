<?php

namespace App\Support;

use App\Models\FundSetting;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Exact decimal money helpers. All stored amounts are in Rial (IRR).
 * UI input/output may be in Toman (IRT) depending on fund settings.
 */
final class Money
{
    public const SCALE = 2;
    public const UNIT_SCALE = 8;

    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if (is_float($value)) {
            $value = number_format($value, 8, '.', '');
        }

        return BigDecimal::of((string) $value);
    }

    public static function zero(): BigDecimal
    {
        return BigDecimal::zero();
    }

    /** Round to storage precision for IRR amounts. */
    public static function irr(mixed $value): BigDecimal
    {
        return self::of($value)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function units(mixed $value): BigDecimal
    {
        return self::of($value)->toScale(self::UNIT_SCALE, RoundingMode::Down);
    }

    public static function str(mixed $value, int $scale = self::SCALE): string
    {
        return (string) self::of($value)->toScale($scale, RoundingMode::HalfUp);
    }

    public static function div(mixed $a, mixed $b, int $scale = self::UNIT_SCALE, RoundingMode $mode = RoundingMode::HalfUp): BigDecimal
    {
        return self::of($a)->dividedBy(self::of($b), $scale, $mode);
    }

    public static function displayFactor(): int
    {
        return self::displayCurrency() === 'IRT' ? 10 : 1;
    }

    public static function displayCurrency(): string
    {
        try {
            return FundSetting::current()->display_currency ?: 'IRT';
        } catch (\Throwable) {
            return 'IRT';
        }
    }

    public static function displayUnitLabel(): string
    {
        return self::displayCurrency() === 'IRT' ? 'تومان' : 'ریال';
    }

    /** Normalise user typed numbers: Persian/Arabic digits, thousands separators. */
    public static function normalizeNumber(?string $input): string
    {
        $input = trim((string) $input);
        $input = strtr($input, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٬' => '', ',' => '', '،' => '', ' ' => '', "\u{066B}" => '.', '/' => '.',
        ]);

        return $input;
    }

    public static function isNumeric(?string $input): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d+)?$/', self::normalizeNumber($input));
    }

    /** Parse an amount typed in the display currency into IRR. */
    public static function fromInput(?string $input): BigDecimal
    {
        $n = self::normalizeNumber($input);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $n)) {
            throw new InvalidArgumentException('Invalid amount');
        }

        return self::irr(BigDecimal::of($n)->multipliedBy(self::displayFactor()));
    }

    /** Convert an IRR amount into the display currency number (string). */
    public static function toDisplayNumber(mixed $irr): string
    {
        $v = self::of($irr)->dividedBy(self::displayFactor(), 2, RoundingMode::HalfUp);

        return (string) $v->strippedOfTrailingZeros();
    }

    public static function format(mixed $irr, bool $withUnit = true): string
    {
        $v = self::of($irr)->dividedBy(self::displayFactor(), 1, RoundingMode::HalfUp);
        $decimals = $v->getFractionalPart()->isZero() ? 0 : 1;
        $s = self::groupDigits((string) $v->toScale($decimals, RoundingMode::HalfUp));

        // LRM keeps a leading minus sign attached to the digits inside RTL text.
        if (str_starts_with($s, '-')) {
            $s = "\u{200E}".$s;
        }

        return $withUnit ? $s.' '.self::displayUnitLabel() : $s;
    }

    public static function formatNumber(mixed $value, int $decimals = 0): string
    {
        $v = self::of($value)->toScale($decimals, RoundingMode::HalfUp);
        $s = (string) $v;
        if ($decimals > 0 && str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }

        return self::groupDigits($s);
    }

    public static function groupDigits(string $s): string
    {
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$int, $frac] = array_pad(explode('.', $s, 2), 2, null);
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);

        return ($neg ? '-' : '').$int.($frac !== null && $frac !== '' ? '.'.$frac : '');
    }

    public static function percent(mixed $value, int $decimals = 2): string
    {
        return self::formatNumber($value, $decimals).'٪';
    }
}
