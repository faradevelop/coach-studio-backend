<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramExerciseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programExerciseId' => $this->program_exercise_id,
            'exerciseId' => $this->exercise_id,
            'order' => $this->order_index,
            'reps' => $this->reps,
            'tempo' => $this->tempo,
            'description' => $this->description,
        ];
    }
}