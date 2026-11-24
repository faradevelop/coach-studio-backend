<?php

namespace App\Models;

use App\Enums\ExerciseType;
use App\Enums\MuscleRole;
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

    /** All muscles, with the pivot `role` (use for writing via sync()). */
    public function muscles(): BelongsToMany
    {
        return $this->belongsToMany(Muscle::class, 'exercise_muscle')->withPivot('role');
    }

    /** Target muscles. */
    public function primaryMuscles(): BelongsToMany
    {
        return $this->muscles()->wherePivot('role', MuscleRole::Primary->value);
    }

    /** Assisting muscles (optional, may be empty). */
    public function secondaryMuscles(): BelongsToMany
    {
        return $this->muscles()->wherePivot('role', MuscleRole::Secondary->value);
    }
}
