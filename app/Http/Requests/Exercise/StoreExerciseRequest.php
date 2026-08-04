<?php

namespace App\Http\Requests\Exercise;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // target_muscle / difficulty / equipment are intentionally free
            // strings (Decision 3) — no Rule::in against a fixed list.
            'targetMuscle' => ['required', 'string', 'max:100'],
            'difficulty' => ['required', 'string', 'max:50'],
            'equipment' => ['required', 'string', 'max:100'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'videoUrl' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'mistakes' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}