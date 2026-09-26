<?php

namespace Database\Factories;

use App\Models\Muscle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MuscleFactory extends Factory
{
    protected $model = Muscle::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug' => Str::slug($name, '_'),
            'name' => $name,
        ];
    }
}
