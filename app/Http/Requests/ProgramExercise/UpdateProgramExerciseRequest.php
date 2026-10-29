<?php

namespace App\Http\Requests\ProgramExercise;

use App\Http\Requests\ProgramExercise\Concerns\ProgramExerciseRules;
use App\Models\ProgramExerciseItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramExerciseRequest extends FormRequest
{
    use ProgramExerciseRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Exercises already in this block stay valid even if they were deactivated later.
        $existing = ProgramExerciseItem::where('program_exercise_id', $this->route('id'))
            ->pluck('exercise_id')
            ->all();

        return $this->commonRules() + [
            'items.*.exerciseId' => [
                'required', 'uuid',
                Rule::exists('exercises', 'id')->where(
                    fn ($q) => $q->where('is_active', true)->orWhereIn('id', $existing)
                ),
            ],
        ];
    }
}
