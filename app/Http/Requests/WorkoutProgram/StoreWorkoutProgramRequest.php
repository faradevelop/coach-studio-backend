<?php

namespace App\Http\Requests\WorkoutProgram;

use App\Services\WorkoutProgramDayService;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => TextNormalizer::normalizeLetters($this->input('title'))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'goal' => ['required', 'string', Rule::in([
                'hypertrophy', 'strength', 'fatLoss', 'endurance', 'rehabilitation',
            ])],
            'level' => ['required', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'daysPerWeek' => ['required', 'integer', 'min:1', 'max:' . WorkoutProgramDayService::MAX_DAYS],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
