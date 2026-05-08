<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Validates Philippine phone number formats
 * Accepts: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX
 */
class PhilippinePhone implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);

        // Philippine phone formats:
        // 09XXXXXXXXX (11 digits)
        // +639XXXXXXXXX (13 digits)
        // 639XXXXXXXXX (12 digits)
        return preg_match('/^(09|\+639|639)\d{9}$/', $cleaned) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be a valid Philippine phone number (e.g., 09171234567, +639171234567).';
    }
}
