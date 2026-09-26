<?php

namespace App\Models;

use App\Enums\ExerciseType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Exercise extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'type',
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
        'type' => ExerciseType::class,
        'is_active' => 'boolean',
    ];

    public function muscles(): BelongsToMany
    {
        return $this->belongsToMany(Muscle::class, 'exercise_muscle');
    }
}
