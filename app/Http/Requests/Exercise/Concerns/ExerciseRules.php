<?php

namespace App\Http\Requests\Exercise\Concerns;

use App\Enums\Difficulty;
use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Support\TextNormalizer;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/** Shared by Store/Update so the exercise rules live in one place. */
trait ExerciseRules
{
    protected function prepareForValidation(): void
    {
        // Only letters are normalized (ي→ی, ك→ک); digits are left as typed.
        if (is_string($this->input('name'))) {
            $this->merge(['name' => TextNormalizer::normalizeLetters($this->input('name'))]);
        }
    }

    protected function exerciseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ExerciseType::class)],
            'difficulty' => ['required', new Enum(Difficulty::class)],
            'equipment' => ['required', new Enum(Equipment::class)],
            // Target muscles: at least one.
            'primaryMuscleSlugs' => ['required', 'array', 'min:1'],
            'primaryMuscleSlugs.*' => ['required', 'string', 'distinct', 'exists:muscles,slug'],
            // Assisting muscles: optional (absent / null / [] all mean "none").
            'secondaryMuscleSlugs' => ['nullable', 'array'],
            'secondaryMuscleSlugs.*' => ['required', 'string', 'distinct', 'exists:muscles,slug'],
            'imageUrl' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'videoUrl' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'mistakes' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $primary = $this->input('primaryMuscleSlugs');
            $secondary = $this->input('secondaryMuscleSlugs');

            // Type errors are already reported by the base rules.
            if (! is_array($primary) || ! is_array($secondary)) {
                return;
            }

            // A muscle cannot be both target and assisting in the same exercise.
            if (array_intersect(array_filter($primary, 'is_string'), array_filter($secondary, 'is_string')) !== []) {
                $validator->errors()->add(
                    'secondaryMuscleSlugs',
                    'A muscle cannot be both a primary and a secondary muscle.'
                );
            }
        });
    }
}
