<?php

namespace App\Http\Requests\Exercise\Concerns;

use App\Enums\Difficulty;
use App\Enums\Equipment;
use App\Enums\ExerciseType;
use App\Support\TextNormalizer;
use Illuminate\Validation\Rules\Enum;

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
            'muscleSlugs' => ['required', 'array', 'min:1'],
            'muscleSlugs.*' => ['required', 'string', 'distinct', 'exists:muscles,slug'],
            'imageUrl' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'videoUrl' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'mistakes' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
