<?php

namespace Database\Factories;

use App\Enums\ExerciseType;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => ExerciseType::Strength->value,
            'difficulty' => 'Beginner',
            'equipment' => 'Barbell',
            'is_active' => true,
        ];
    }
}
