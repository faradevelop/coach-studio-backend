<?php

namespace App\Support;

class PasswordRules
{
    /**
     * Single source of truth for the password policy. $confirmed adds the
     * matching "{field}_confirmation" requirement — true for every current
     * caller, kept as a parameter in case a future caller needs to opt out.
     */
    public static function rules(bool $confirmed = true): array
    {
        $rules = ['required', 'string', 'min:8'];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
