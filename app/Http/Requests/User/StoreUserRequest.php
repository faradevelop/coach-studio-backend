<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\UserProfileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\PasswordRules;

class StoreUserRequest extends FormRequest
{
    use UserProfileRules;

    public function authorize(): bool { return true; } // enforced via Policy in controller

    public function rules(): array
    {
        return $this->profileRules() + [
            'username' => ['required', 'string', 'max:50', 'regex:/^[^@\s]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordRules::rules(),
            'role' => ['required', 'string', Rule::in(['admin', 'coach'])],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
