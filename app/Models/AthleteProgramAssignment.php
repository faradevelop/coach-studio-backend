<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteProgramAssignment extends Model
{
    use HasUuids;

    protected $fillable = [
        'athlete_id',
        'workout_program_id',
        'source_program_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function workoutProgram(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgram::class, 'workout_program_id');
    }

    public function sourceProgram(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgram::class, 'source_program_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }
}
