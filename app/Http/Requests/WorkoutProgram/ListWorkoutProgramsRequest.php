<?php

namespace App\Http\Requests\WorkoutProgram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWorkoutProgramsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is enforced by the scoped query in the controller
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'template', 'assigned'])],
            'goal' => ['nullable', Rule::in([
                'hypertrophy', 'strength', 'fatLoss', 'endurance', 'rehabilitation',
            ])],
            'level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
        ];
    }
}
