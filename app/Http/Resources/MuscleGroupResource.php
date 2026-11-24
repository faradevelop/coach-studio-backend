<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MuscleGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'nameEn' => $this->name_en,
            'sortOrder' => $this->sort_order,
            'muscles' => MuscleResource::collection($this->whenLoaded('muscles')),
        ];
    }
}
