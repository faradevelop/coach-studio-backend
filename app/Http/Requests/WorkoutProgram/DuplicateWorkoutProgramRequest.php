<?php

namespace App\Http\Requests\WorkoutProgram;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateWorkoutProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: falls back to "Copy of {original title}" in the service
            // when omitted or blank.
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
