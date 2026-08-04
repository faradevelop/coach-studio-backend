<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramExerciseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'programExercise' => new ProgramExerciseResource($this->resource),
            'items' => $this->items->map(fn ($item) => [
                'item' => new ProgramExerciseItemResource($item),
                'exercise' => new ExerciseResource($item->exercise),
            ]),
        ];
    }
}