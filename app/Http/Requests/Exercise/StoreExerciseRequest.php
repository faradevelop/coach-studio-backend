<?php

namespace App\Http\Requests\Exercise;

use App\Http\Requests\Exercise\Concerns\ExerciseRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    use ExerciseRules;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return $this->exerciseRules();
    }
}
