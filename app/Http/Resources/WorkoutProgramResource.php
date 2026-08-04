<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'goal' => $this->goal,
            'level' => $this->level,
            'daysPerWeek' => $this->days_per_week,
            'notes' => $this->notes,
            'isTemplate' => (bool) $this->is_template,
        ];
    }
}