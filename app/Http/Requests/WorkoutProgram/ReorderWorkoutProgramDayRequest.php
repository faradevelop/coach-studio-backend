<?php

namespace App\Http\Requests\WorkoutProgram;

use App\Services\WorkoutProgramDayService;
use Illuminate\Foundation\Http\FormRequest;

class ReorderWorkoutProgramDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership enforced in the controller via scoped query + Policy
    }

    public function rules(): array
    {
        return [
            // Target 1-based position of the day. The upper bound against the
            // program's actual days_per_week is enforced in the service under
            // the row lock.
            'order' => ['required', 'integer', 'min:1', 'max:' . WorkoutProgramDayService::MAX_DAYS],
        ];
    }
}
