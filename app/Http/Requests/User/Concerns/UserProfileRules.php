<?php

namespace App\Http\Requests\User\Concerns;

use App\Support\TextNormalizer;

/** Shared by Store/Update so the profile rules live in one place. */
trait UserProfileRules
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['firstName', 'lastName', 'phone'] as $key) {
            if (is_string($this->input($key))) {
                $normalized[$key] = TextNormalizer::normalize($this->input($key));
            }
        }

        // Email is optional: a blank value means "no email" (null).
        if (is_string($this->input('email'))) {
            $email = trim($this->input('email'));
            $normalized['email'] = $email === '' ? null : $email;
        }

        $this->merge($normalized);
    }

    protected function profileRules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }
}
