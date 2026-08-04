<?php

namespace App\Http\Requests\WorkoutProgram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkoutProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'goal' => ['required', 'string', Rule::in([
                'hypertrophy', 'strength', 'fatLoss', 'endurance', 'rehabilitation',
            ])],
            'level' => ['required', 'string', Rule::in([
                'beginner', 'intermediate', 'advanced',
            ])],
            // Flutter hides this field in edit mode but still submits the
            // pre-filled original value — kept required to match that behavior.
            'daysPerWeek' => ['required', 'integer', 'min:1', 'max:255'],
            'notes' => ['nullable', 'string'],
            'isTemplate' => ['sometimes', 'boolean'],
        ];
    }
}