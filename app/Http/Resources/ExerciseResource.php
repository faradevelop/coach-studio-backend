<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'targetMuscle' => $this->target_muscle,
            'difficulty' => $this->difficulty,
            'equipment' => $this->equipment,
            'imageUrl' => $this->image_url,
            'videoUrl' => $this->video_url,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'mistakes' => $this->mistakes,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}