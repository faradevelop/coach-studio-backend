<?php

namespace App\Http\Requests\Athlete;

use Illuminate\Foundation\Http\FormRequest;

class AssignAthleteProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership of athlete and source program enforced in controller
    }

    public function rules(): array
    {
        return [
            'sourceProgramId' => ['required', 'uuid', 'exists:workout_programs,id'],
            // Optional title for the athlete's copy; defaults to the source title.
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
