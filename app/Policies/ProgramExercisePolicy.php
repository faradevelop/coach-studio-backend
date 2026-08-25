<?php

namespace App\Policies;

use App\Models\ProgramExercise;
use App\Models\User;
use App\Models\WorkoutProgram;

class ProgramExercisePolicy
{
    // No model instance exists yet at create-time, so ownership is checked
    // against the parent WorkoutProgram instead.
    public function create(User $user, WorkoutProgram $workoutProgram): bool
    {
        return $user->isAdmin() || $workoutProgram->user_id === $user->id;
    }

    public function update(User $user, ProgramExercise $programExercise): bool
    {
        return $user->isAdmin() || $programExercise->workoutProgram->user_id === $user->id;
    }

    public function delete(User $user, ProgramExercise $programExercise): bool
    {
        return $this->update($user, $programExercise);
    }
}
