<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\PasswordRules;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'newPassword' => PasswordRules::rules(),
            // expects a matching "newPassword_confirmation" field
        ];
    }
}
