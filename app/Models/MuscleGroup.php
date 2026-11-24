<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MuscleGroup extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['slug', 'name', 'name_en', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function muscles(): HasMany
    {
        return $this->hasMany(Muscle::class)->orderBy('sort_order');
    }
}
