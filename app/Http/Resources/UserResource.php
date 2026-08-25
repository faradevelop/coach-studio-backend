<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
        // password is never included — $hidden on the model is a second layer of defense
    }
}
