<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramExercise extends Model
{
    use HasUuids;

    protected $fillable = [
        'workout_program_id',
        'day',
        'order_index',
        'sets',
        'rest',
        'training_system',
    ];

    protected $casts = [
        'day' => 'integer',
        'order_index' => 'integer',
    ];

    public function workoutProgram(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgram::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgramExerciseItem::class)->orderBy('order_index');
    }
}