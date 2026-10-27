<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\UserProfileRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use UserProfileRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id');

        return $this->profileRules() + [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', 'string', Rule::in(['admin', 'coach'])],
            'isActive' => ['sometimes', 'boolean'],
            // password is intentionally not editable here — goes through change-password
        ];
    }
}
