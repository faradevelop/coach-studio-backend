<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    // viewAny/view intentionally omitted — every authenticated user
    // (coach or admin) may read the catalog; only writes are restricted.

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $user->isAdmin();
    }
}
