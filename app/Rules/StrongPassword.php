<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Validates strong password requirements
 * Requirements:
 * - At least 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 */
class StrongPassword implements Rule
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
        if (empty($value) || !is_string($value)) {
            return false;
        }

        // At least 8 characters
        if (strlen($value) < 8) {
            return false;
        }

        // Maximum 64 characters (security best practice)
        if (strlen($value) > 64) {
            return false;
        }

        // At least one uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            return false;
        }

        // At least one lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            return false;
        }

        // At least one number
        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }

        // At least one special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The :attribute must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    }
}
