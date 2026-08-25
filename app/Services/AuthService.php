<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(string $identifier, string $password): array
    {
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $identifier)->first()
            : User::where('username', $identifier)->first();

        // Generic message regardless of which check failed — avoids
        // revealing whether a given username/email exists.
        if (!$user || !$user->is_active || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
        $user->tokens()->delete(); // revoke all sessions after a password change
    }

    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();

        // Externally identical response either way — but the actual reset
        // link is only ever generated/sent for an existing, active account.
        if ($user && $user->is_active) {
            Password::sendResetLink(['email' => $email]);
        }
    }

    public function resetPassword(string $email, string $token, string $newPassword): void
    {
        $user = User::where('email', $email)->first();

        if (!$user || !$user->is_active) {
            // Same generic wording as an invalid token — does not reveal
            // that the account exists but is inactive.
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid.'],
            ]);
        }

        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $newPassword],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => [__($status)]]);
        }
    }
}
