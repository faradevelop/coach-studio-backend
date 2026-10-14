<?php

namespace App\Policies;

use App\Models\Athlete;
use App\Models\User;

class AthletePolicy
{
    public function view(User $user, Athlete $athlete): bool
    {
        return $user->isCoach() && $athlete->coach_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isCoach();
    }

    // Also covers archive / restore / assign program.
    public function update(User $user, Athlete $athlete): bool
    {
        return $this->view($user, $athlete);
    }
}
