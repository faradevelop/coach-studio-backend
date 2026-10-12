<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AthleteProgramAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'athleteId' => $this->athlete_id,
            'programId' => $this->workout_program_id,
            'sourceProgramId' => $this->source_program_id,
            'assignedBy' => $this->assigned_by,
            'assignedAt' => $this->assigned_at?->toIso8601String(),
            'endedAt' => $this->ended_at?->toIso8601String(),
            'isCurrent' => $this->ended_at === null,
            'program' => $this->when(
                $this->resource->relationLoaded('workoutProgram'),
                fn () => $this->workoutProgram ? new WorkoutProgramResource($this->workoutProgram) : null
            ),
            // The source may have been deleted later (FK is nullOnDelete).
            'sourceProgram' => $this->when(
                $this->resource->relationLoaded('sourceProgram'),
                fn () => $this->sourceProgram
                    ? ['id' => $this->sourceProgram->id, 'title' => $this->sourceProgram->title]
                    : null
            ),
        ];
    }
}
