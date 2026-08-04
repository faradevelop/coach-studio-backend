<?php

namespace App\Http\Requests\ProgramExercise;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProgramExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Target 1-based position within the ProgramExercise's current day.
            'order' => ['required', 'integer', 'min:1'],
        ];
    }
}