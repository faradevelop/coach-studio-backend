<?php

namespace App\Http\Requests\Athlete;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAthletesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'hasProgram' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'sort' => ['nullable', Rule::in(['name', 'createdAt'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
