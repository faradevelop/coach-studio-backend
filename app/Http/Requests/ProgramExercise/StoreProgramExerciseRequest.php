<?php

namespace App\Http\Requests\ProgramExercise;

use App\Http\Requests\ProgramExercise\Concerns\ProgramExerciseRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramExerciseRequest extends FormRequest
{
    use ProgramExerciseRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->commonRules() + [
            'items.*.exerciseId' => [
                'required', 'uuid',
                Rule::exists('exercises', 'id')->where('is_active', true),
            ],
        ];
    }
}
