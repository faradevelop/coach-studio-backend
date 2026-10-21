<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignmentLoaded = $this->resource->relationLoaded('athleteAssignment');
        $assignment = $assignmentLoaded ? $this->athleteAssignment : null;

        // 'template' = draft owned by the coach, 'assigned' = athlete-specific copy.
        // When the relation is not loaded we fall back to the flag.
        $isAssigned = $assignmentLoaded ? $assignment !== null : !$this->is_template;

        $athlete = $assignment && $assignment->relationLoaded('athlete') ? $assignment->athlete : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'goal' => $this->goal,
            'level' => $this->level,
            'daysPerWeek' => $this->days_per_week,
            'notes' => $this->notes,
            'isTemplate' => (bool) $this->is_template,
            'status' => $isAssigned ? 'assigned' : 'template',
            // Present (possibly null) only when athleteAssignment is eager-loaded.
            'athleteId' => $this->when($assignmentLoaded, fn () => $assignment?->athlete_id),
            'athlete' => $this->when($assignmentLoaded, fn () => $athlete ? [
                'id' => $athlete->id,
                'firstName' => $athlete->first_name,
                'lastName' => $athlete->last_name,
                'fullName' => trim($athlete->first_name . ' ' . $athlete->last_name),
                'isArchived' => $athlete->archived_at !== null,
            ] : null),
            'assignment' => $this->when($assignmentLoaded, fn () => $assignment ? [
                'id' => $assignment->id,
                'sourceProgramId' => $assignment->source_program_id,
                'assignedAt' => $assignment->assigned_at?->toIso8601String(),
                'endedAt' => $assignment->ended_at?->toIso8601String(),
                'isCurrent' => $assignment->ended_at === null,
            ] : null),
        ];
    }
}
