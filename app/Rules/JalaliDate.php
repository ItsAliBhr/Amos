<?php

namespace App\Rules;

use App\Support\Jalali;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class JalaliDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Jalali::isValid($value)) {
            $fail(':attribute باید تاریخ شمسی معتبر مانند 1404/05/12 باشد.');
        }
    }
}
