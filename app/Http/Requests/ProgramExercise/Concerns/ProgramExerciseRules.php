<?php

namespace App\Http\Requests\ProgramExercise\Concerns;

use App\Services\WorkoutProgramDayService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ProgramExerciseRules
{
    protected function commonRules(): array
    {
        return [
            'workoutId' => ['required', 'uuid', 'exists:workout_programs,id'],
            // Upper bound = max days of a program; also keeps the tinyint column from overflowing (500).
            'day' => ['required', 'integer', 'min:1', 'max:' . WorkoutProgramDayService::MAX_DAYS],
            'sets' => ['required', 'string', 'max:20'],
            'rest' => ['required', 'string', 'max:20'],
            'trainingSystem' => ['required', 'string', Rule::in(['normal', 'superSet'])],
            'items' => ['required', 'array', 'min:1', 'max:2'],
            'items.*.reps' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.reps.*' => ['required', 'string', 'max:20'],
            'items.*.tempo' => ['required', 'string', 'max:20'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items');
            $trainingSystem = $this->input('trainingSystem');

            // Wrong types are already reported by the base rules (422), never a TypeError (500).
            if (! is_array($items) || ! in_array($trainingSystem, ['normal', 'superSet'], true)) {
                return;
            }

            $expected = $trainingSystem === 'superSet' ? 2 : 1;

            if (count($items) !== $expected) {
                $validator->errors()->add(
                    'items',
                    "A '{$trainingSystem}' training system requires exactly {$expected} item(s)."
                );
            }
        });
    }
}
