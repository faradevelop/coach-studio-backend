<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'], // username OR email
            'password' => ['required', 'string'],
        ];
    }
}
