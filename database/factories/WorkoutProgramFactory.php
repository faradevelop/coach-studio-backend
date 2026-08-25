<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutProgramFactory extends Factory
{
    protected $model = WorkoutProgram::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'goal' => 'strength',
            'level' => 'intermediate',
            'days_per_week' => 3,
            'notes' => null,
            'is_template' => true,
        ];
    }
}
