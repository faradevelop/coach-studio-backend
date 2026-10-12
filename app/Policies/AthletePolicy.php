<?php

namespace App\Policies;

use App\Models\Athlete;
use App\Models\User;

class AthletePolicy
{
    public function view(User $user, Athlete $athlete): bool
    {
        return $user->isAdmin() || $athlete->coach_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // coach or admin; the target coach is resolved in AthleteService
    }

    // Also covers archive / restore / assign program.
    public function update(User $user, Athlete $athlete): bool
    {
        return $user->isAdmin() || $athlete->coach_id === $user->id;
    }
}
