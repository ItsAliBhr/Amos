<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Gregorian <-> Jalali (Shamsi) conversion. Database stores Gregorian; UI shows Jalali.
 */
final class Jalali
{
    public const MONTHS = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }

        return [$gy, $gm, $gd];
    }

    public static function isLeap(int $jy): bool
    {
        [$gy, $gm, $gd] = self::toGregorian($jy, 12, 30);
        [$y, $m, $d] = self::toJalali($gy, $gm, $gd);

        return $y === $jy && $m === 12 && $d === 30;
    }

    public static function daysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isLeap($jy) ? 30 : 29;
    }

    public static function fromDate(DateTimeInterface|string|null $date): ?array
    {
        if ($date === null || $date === '') {
            return null;
        }
        $c = $date instanceof DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::parse($date);
        $c = $c->setTimezone(config('app.display_timezone', 'Asia/Tehran'));

        return self::toJalali((int) $c->format('Y'), (int) $c->format('n'), (int) $c->format('j'));
    }

    public static function format(DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::parse($date);
        if ($withTime) {
            $c = $c->setTimezone(config('app.display_timezone', 'Asia/Tehran'));
        }
        [$y, $m, $d] = self::toJalali((int) $c->format('Y'), (int) $c->format('n'), (int) $c->format('j'));
        $s = sprintf('%04d/%02d/%02d', $y, $m, $d);
        if ($withTime) {
            $s .= ' '.$c->format('H:i');
        }

        return $s;
    }

    public static function monthName(int $m): string
    {
        return self::MONTHS[$m] ?? (string) $m;
    }

    public static function monthLabel(int $y, int $m): string
    {
        return self::monthName($m).' '.$y;
    }

    /** Parse a Jalali date string (1404/05/12, Persian digits allowed) to a Gregorian date. */
    public static function parse(?string $input): CarbonImmutable
    {
        $s = Money::normalizeNumber(str_replace(['-', '.'], '/', (string) $input));
        $s = str_replace('.', '/', $s);
        if (! preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $s, $m)) {
            throw new InvalidArgumentException('Invalid Jalali date');
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > self::daysInMonth($jy, $jm) || $jy < 1300 || $jy > 1500) {
            throw new InvalidArgumentException('Invalid Jalali date');
        }
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);

        return CarbonImmutable::create($gy, $gm, $gd, 0, 0, 0);
    }

    public static function isValid(?string $input): bool
    {
        try {
            self::parse($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function today(): string
    {
        return self::format(CarbonImmutable::now(config('app.display_timezone', 'Asia/Tehran'))->format('Y-m-d'));
    }

    /** Current Jalali [year, month]. */
    public static function currentYearMonth(): array
    {
        [$y, $m] = self::fromDate(CarbonImmutable::now());

        return [$y, $m];
    }

    public static function addMonths(int $y, int $m, int $delta): array
    {
        $idx = $y * 12 + ($m - 1) + $delta;

        return [intdiv($idx, 12), ($idx % 12) + 1];
    }

    /** Gregorian date range [start, end] covering a Jalali month. */
    public static function monthRange(int $jy, int $jm): array
    {
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, 1);
        $start = CarbonImmutable::create($gy, $gm, $gd);
        $end = $start->addDays(self::daysInMonth($jy, $jm) - 1);

        return [$start, $end];
    }
}
