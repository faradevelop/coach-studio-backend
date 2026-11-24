<?php

namespace Database\Factories;

use App\Models\MuscleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MuscleGroupFactory extends Factory
{
    protected $model = MuscleGroup::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'name_en' => $name,
            'sort_order' => 0,
        ];
    }
}
