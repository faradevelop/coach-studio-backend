<?php

namespace Database\Factories;

use App\Models\Muscle;
use App\Models\MuscleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MuscleFactory extends Factory
{
    protected $model = Muscle::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'muscle_group_id' => MuscleGroup::factory(),
            'slug' => Str::slug($name),
            'name' => $name,
            'name_en' => $name,
            'sort_order' => 0,
        ];
    }
}
