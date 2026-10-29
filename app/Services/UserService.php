<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function update(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data) {
            $admins = $this->lockAdmins();
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();

            $newRole = $data['role'] ?? $target->role;
            $newIsActive = $data['isActive'] ?? $target->is_active;

            if ($actor->id === $target->id) {
                $this->guardSelfChange($newRole, $newIsActive);
            }

            $this->guardAdminInvariant($admins, $target, $newRole, $newIsActive);

            $wasActive = (bool) $target->is_active;

            $target->update([
                'username' => $data['username'],
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null, // optional: absent/blank clears it
                'role' => $newRole,
                'is_active' => $newIsActive,
            ]);

            // Deactivation by ANY route must cut access immediately.
            // Re-activation does not bring old tokens back: they are gone.
            if ($wasActive && ! $newIsActive) {
                $target->tokens()->delete();
            }

            return $target->fresh();
        });
    }

    public function deactivate(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target) {
            $admins = $this->lockAdmins();
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($actor->id === $target->id) {
                throw ValidationException::withMessages([
                    'user' => ['You cannot deactivate your own account.'],
                ]);
            }

            $this->guardAdminInvariant($admins, $target, $target->role, false);

            $target->update(['is_active' => false]); // account deactivation — not a soft delete
            $target->tokens()->delete();
        });
    }

    /** Always lock the admin set first, in id order (prevents deadlocks). */
    private function lockAdmins(): Collection
    {
        return User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
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
     * The system must always retain at least one active admin.
     * $admins was read under lock, so the count cannot race.
     */
    private function guardAdminInvariant(Collection $admins, User $target, string $resultingRole, bool $resultingActive): void
    {
        $wasActiveAdmin = $target->role === 'admin' && $target->is_active;
        $willRemainActiveAdmin = $resultingRole === 'admin' && $resultingActive;

        if ($wasActiveAdmin && !$willRemainActiveAdmin) {
            if ($admins->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['The last active admin cannot be demoted or deactivated.'],
                ]);
            }
        }
    }
}
