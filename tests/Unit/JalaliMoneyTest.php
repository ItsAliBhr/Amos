<?php

namespace Tests\Unit;

use App\Support\Jalali;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class JalaliMoneyTest extends TestCase
{
    public function test_gregorian_jalali_round_trip(): void
    {
        $this->assertSame([1404, 1, 1], Jalali::toJalali(2025, 3, 21));
        $this->assertSame([1403, 12, 30], Jalali::toJalali(2025, 3, 20)); // 1403 is a leap year
        $this->assertSame([2025, 3, 21], Jalali::toGregorian(1404, 1, 1));
        $this->assertSame([1405, 8, 27], Jalali::toJalali(2026, 11, 18));
        for ($d = 0; $d < 800; $d += 7) {
            $g = (new \DateTimeImmutable('2024-01-01'))->modify("+{$d} days");
            [$jy, $jm, $jd] = Jalali::toJalali((int) $g->format('Y'), (int) $g->format('n'), (int) $g->format('j'));
            $this->assertSame([(int) $g->format('Y'), (int) $g->format('n'), (int) $g->format('j')], Jalali::toGregorian($jy, $jm, $jd));
        }
        $this->assertTrue(Jalali::isLeap(1403));
        $this->assertFalse(Jalali::isLeap(1404));
        $this->assertFalse(Jalali::isValid('1404/12/30'));
        $this->assertTrue(Jalali::isValid('۱۴۰۳/۱۲/۳۰'));
    }

    public function test_number_normalisation_and_grouping(): void
    {
        $this->assertSame('1234567.5', Money::normalizeNumber('۱,۲۳۴,۵۶۷.۵'));
        $this->assertSame('-1,234,567.89', Money::groupDigits('-1234567.89'));
        $this->assertSame('0.30000000', (string) Money::of('0.1')->plus('0.2')->toScale(8));
        $this->assertFalse(Money::isNumeric('12a'));
    }
}
