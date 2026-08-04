<?php

namespace App\Http\Requests\ProgramExercise;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProgramExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // workoutId is accepted for shape-compatibility with the Dart
            // ProgramExercise entity but is never applied on update — a
            // ProgramExercise never moves between programs in the existing
            // Flutter flow, only between days within the same program.
            'workoutId' => ['required', 'uuid', 'exists:workout_programs,id'],
            'day' => ['required', 'integer', 'min:1'],
            'sets' => ['required', 'string', 'max:20'],
            'rest' => ['required', 'string', 'max:20'],
            'trainingSystem' => ['required', 'string', Rule::in(['normal', 'superSet'])],
            'items' => ['required', 'array', 'min:1', 'max:2'],
            'items.*.exerciseId' => ['required', 'uuid', 'exists:exercises,id'],
            'items.*.reps' => ['required', 'string', 'max:20'],
            'items.*.tempo' => ['required', 'string', 'max:20'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $trainingSystem = $this->input('trainingSystem');
            $itemCount = count($this->input('items', []));
            $expected = $trainingSystem === 'superSet' ? 2 : 1;

            if ($trainingSystem !== null && $itemCount !== $expected) {
                $validator->errors()->add(
                    'items',
                    "A '{$trainingSystem}' training system requires exactly {$expected} item(s)."
                );
            }
        });
    }
}