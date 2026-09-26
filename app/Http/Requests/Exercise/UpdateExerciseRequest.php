<?php

namespace App\Http\Requests\Exercise;

use App\Enums\ExerciseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ExerciseType::class)],
            'difficulty' => ['required', 'string', 'max:50'],
            'equipment' => ['required', 'string', 'max:100'],
            'muscleSlugs' => ['required', 'array', 'min:1'],
            'muscleSlugs.*' => ['required', 'string', 'distinct', 'exists:muscles,slug'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'videoUrl' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'mistakes' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
