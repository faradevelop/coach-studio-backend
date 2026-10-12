<?php

namespace Database\Factories;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AthleteFactory extends Factory
{
    protected $model = Athlete::class;

    public function definition(): array
    {
        return [
            'coach_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => 'male',
            'date_of_birth' => '1995-01-01',
            'phone' => $this->faker->numerify('09#########'),
            'email' => $this->faker->unique()->safeEmail(),
            'height_cm' => 175,
            'weight_kg' => 75.5,
            'fitness_goal' => 'strength',
            'experience_level' => 'beginner',
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
