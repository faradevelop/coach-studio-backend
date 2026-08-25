<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutProgram;

class WorkoutProgramPolicy
{
    public function view(User $user, WorkoutProgram $program): bool
    {
        return $user->isAdmin() || $program->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // any authenticated user (coach or admin) may create their own
    }

    public function update(User $user, WorkoutProgram $program): bool
    {
        return $user->isAdmin() || $program->user_id === $user->id;
    }

    public function delete(User $user, WorkoutProgram $program): bool
    {
        return $user->isAdmin() || $program->user_id === $user->id;
    }
}
