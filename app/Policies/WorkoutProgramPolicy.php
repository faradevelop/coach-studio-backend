<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutProgram;

class WorkoutProgramPolicy
{
    // Admins have no access to coach programs: owner coaches only.
    public function view(User $user, WorkoutProgram $program): bool
    {
        return $user->isCoach() && $program->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isCoach();
    }

    public function update(User $user, WorkoutProgram $program): bool
    {
        return $this->view($user, $program);
    }

    public function delete(User $user, WorkoutProgram $program): bool
    {
        return $this->view($user, $program);
    }
}
