<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function update(User $actor, User $target, array $data): User
    {
        $newRole = $data['role'] ?? $target->role;
        $newIsActive = $data['isActive'] ?? $target->is_active;

        if ($actor->id === $target->id) {
            $this->guardSelfChange($newRole, $newIsActive);
        }

        $this->guardAdminInvariant($target, $newRole, $newIsActive);

        $target->update([
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $newRole,
            'is_active' => $newIsActive,
        ]);

        return $target->fresh();
    }

    public function deactivate(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        $this->guardAdminInvariant($target, $target->role, false);

        $target->update(['is_active' => false]); // account deactivation — not a soft delete
        $target->tokens()->delete();
    }

    private function guardSelfChange(string $newRole, bool $newIsActive): void
    {
        if ($newRole !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['You cannot change your own role away from admin.'],
            ]);
        }

        if (!$newIsActive) {
            throw ValidationException::withMessages([
                'isActive' => ['You cannot deactivate your own account.'],
            ]);
        }
    }

    /**
     * Enforces: the system must always retain at least one active admin.
     * Only relevant when the target is CURRENTLY an active admin and the
     * requested change would remove that status.
     */
    private function guardAdminInvariant(User $target, string $resultingRole, bool $resultingActive): void
    {
        $wasActiveAdmin = $target->role === 'admin' && $target->is_active;
        $willRemainActiveAdmin = $resultingRole === 'admin' && $resultingActive;

        if ($wasActiveAdmin && !$willRemainActiveAdmin) {
            $activeAdminCount = User::where('role', 'admin')->where('is_active', true)->count();

            if ($activeAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['The last active admin cannot be demoted or deactivated.'],
                ]);
            }
        }
    }
}
