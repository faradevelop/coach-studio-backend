<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'target_muscle',
        'difficulty',
        'equipment',
        'image_url',
        'video_url',
        'description',
        'instructions',
        'mistakes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
