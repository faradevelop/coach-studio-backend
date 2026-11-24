<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Muscle extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'muscle_group_id',
        'slug',
        'name',
        'name_en',
        'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class, 'muscle_group_id');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'exercise_muscle')->withPivot('role');
    }
}
