<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MuscleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'nameEn' => $this->name_en,
            'sortOrder' => $this->sort_order,
            // Only when the relation was eager-loaded (GET /muscles).
            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'slug' => $this->group->slug,
                'name' => $this->group->name,
                'nameEn' => $this->group->name_en,
            ]),
        ];
    }
}
