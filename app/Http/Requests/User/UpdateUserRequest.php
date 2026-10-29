<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\UserProfileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class UpdateUserRequest extends FormRequest
{
    use UserProfileRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id');

        $usernameRules = ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($id)];
        if ($this->input('username') !== User::whereKey($id)->value('username')) {
            $usernameRules[] = 'regex:/^[^@\s]+$/';
        }

        return $this->profileRules() + [
            'username' => $usernameRules,
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', 'string', Rule::in(['admin', 'coach'])],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
