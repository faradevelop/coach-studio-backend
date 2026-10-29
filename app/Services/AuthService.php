<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private const TOKEN_LIFETIME_DAYS = 90;

    public function login(string $identifier, string $password): array
    {
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $identifier)->first()
            : User::where('username', $identifier)->first();

        // When the user doesn't exist we still do one bcrypt operation, so the
        // response time doesn't reveal whether the username/email exists.
        $passwordMatches = $user
            ? Hash::check($password, $user->password)
            : $this->spendHashTime($password);

        // Generic message regardless of which check failed.
        if (! $user || ! $user->is_active || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Fixed 90-day lifetime from issue time. It is NOT extended by activity.
        $token = $user->createToken('api', ['*'], now()->addDays(self::TOKEN_LIFETIME_DAYS))
            ->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    private function spendHashTime(string $password): bool
    {
        Hash::make($password);

        return false;
    }

    public function logout(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
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

        if (! $user || ! $user->is_active) {
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
