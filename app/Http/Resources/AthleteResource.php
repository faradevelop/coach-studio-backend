<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AthleteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coachId' => $this->coach_id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'heightCm' => $this->height_cm,
            'weightKg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'fitnessGoal' => $this->fitness_goal,
            'experienceLevel' => $this->experience_level,
            'injuries' => $this->injuries,
            'physicalLimitations' => $this->physical_limitations,
            'coachNotes' => $this->coach_notes,
            'isArchived' => $this->archived_at !== null,
            'archivedAt' => $this->archived_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            // Present (possibly null) only when the relation was eager-loaded.
            'currentAssignment' => $this->when(
                $this->resource->relationLoaded('currentAssignment'),
                fn () => $this->currentAssignment
                    ? new AthleteProgramAssignmentResource($this->currentAssignment)
                    : null
            ),
        ];
    }
}
