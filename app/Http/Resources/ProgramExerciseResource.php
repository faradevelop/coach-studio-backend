<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workoutId' => $this->workout_program_id,
            'day' => $this->day,
            'order' => $this->order_index,
            'sets' => $this->sets,
            'rest' => $this->rest,
            'trainingSystem' => $this->training_system,
            'items' => ProgramExerciseItemResource::collection($this->items),
        ];
    }
}