<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\PasswordRules;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; } // enforced via Policy in controller

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordRules::rules(),
            'role' => ['required', 'string', Rule::in(['admin', 'coach'])],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
