<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_always_returns_generic_response(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_existing_active_user_receives_a_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'active@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'active@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_nonexistent_email_does_not_reveal_account_existence(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost2@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_inactive_user_does_not_receive_a_usable_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inactive@example.com'])->assertOk();

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_valid_reset_token_works(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnewpass123', $user->fresh()->password));
    }

    public function test_invalid_token_fails(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset2@example.com',
            'token' => 'not-a-real-token',
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertStatus(422);
    }

    public function test_expired_token_fails(): void
    {
        $user = User::factory()->create(['email' => 'reset3@example.com']);
        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', 'reset3@example.com')
            ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 5)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset3@example.com',
            'token' => $token,
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertStatus(422);
    }

    public function test_reset_password_invalidates_all_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset4@example.com']);
        $user->createToken('api');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset4@example.com',
            'token' => $token,
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'reset5@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset5@example.com',
            'token' => $token,
            'password' => 'firstnewpass123',
            'password_confirmation' => 'firstnewpass123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset5@example.com',
            'token' => $token,
            'password' => 'secondnewpass123',
            'password_confirmation' => 'secondnewpass123',
        ])->assertStatus(422);
    }
}
