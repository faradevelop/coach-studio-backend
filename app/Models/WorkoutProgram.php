<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutProgram extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'goal',
        'level',
        'days_per_week',
        'notes',
        'is_template',
    ];

    protected $casts = [
        'days_per_week' => 'integer',
        'is_template' => 'boolean',
    ];

    public function programExercises(): HasMany
    {
        return $this->hasMany(ProgramExercise::class);
    }
}