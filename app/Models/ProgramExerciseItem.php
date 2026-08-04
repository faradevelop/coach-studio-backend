<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramExerciseItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_exercise_id',
        'exercise_id',
        'order_index',
        'reps',
        'tempo',
        'description',
    ];

    protected $casts = [
        'order_index' => 'integer',
    ];

    public function programExercise(): BelongsTo
    {
        return $this->belongsTo(ProgramExercise::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}