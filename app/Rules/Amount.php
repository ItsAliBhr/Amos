<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates an amount typed in the display currency (thousand separators / Persian digits allowed). */
class Amount implements ValidationRule
{
    public function __construct(private bool $allowZero = false)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('مقدار :attribute نامعتبر است.');

            return;
        }
        $n = Money::normalizeNumber((string) $value);
        if (! preg_match('/^\d{1,20}(\.\d{1,8})?$/', $n)) {
            $fail('مقدار :attribute باید عدد مثبت معتبر باشد.');

            return;
        }
        if (! $this->allowZero && Money::of($n)->isZero()) {
            $fail(':attribute باید بزرگ‌تر از صفر باشد.');
        }
    }
}
