<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Athlete extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'coach_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'height_cm',
        'weight_kg',
        'fitness_goal',
        'experience_level',
        'injuries',
        'physical_limitations',
        'coach_notes',
        'archived_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'height_cm' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AthleteProgramAssignment::class);
    }

    /** The single assignment with ended_at IS NULL (if any). */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AthleteProgramAssignment::class)->whereNull('ended_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
