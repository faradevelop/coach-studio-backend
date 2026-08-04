<?php

namespace App\Http\Requests\WorkoutProgram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Values must exactly match Dart enum identifiers (Decision 4).
            'goal' => ['required', 'string', Rule::in([
                'hypertrophy', 'strength', 'fatLoss', 'endurance', 'rehabilitation',
            ])],
            'level' => ['required', 'string', Rule::in([
                'beginner', 'intermediate', 'advanced',
            ])],
            'daysPerWeek' => ['required', 'integer', 'min:1', 'max:255'],
            'notes' => ['nullable', 'string'],
            'isTemplate' => ['sometimes', 'boolean'],
        ];
    }
}