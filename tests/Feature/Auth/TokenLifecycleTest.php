<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function profile(User $u, array $overrides = []): array
    {
        return array_merge([
            'username' => $u->username,
            'firstName' => $u->first_name,
            'lastName' => $u->last_name,
            'phone' => $u->phone,
            'email' => $u->email,
            'role' => $u->role,
            'isActive' => true,
        ], $overrides);
    }

    private function newUserPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'newcoach',
            'firstName' => 'Ali',
            'lastName' => 'Rezaei',
            'phone' => '09121234567',
            'email' => 'newcoach@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'coach',
        ], $overrides);
    }

    public function test_login_issues_a_token_that_expires_in_90_days(): void
    {
        User::factory()->create(['username' => 'coachx', 'password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', ['identifier' => 'coachx', 'password' => 'secret123'])
            ->assertOk();

        $token = PersonalAccessToken::firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $token->expires_at->timestamp, 120);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('api', ['*'], now()->subMinute())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_unexpired_token_is_accepted(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('api', ['*'], now()->addDay())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_inactive_user_with_a_surviving_token_gets_401(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('api')->plainTextToken;
        $user->update(['is_active' => false]); // simulates any path that forgot to revoke

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_deactivating_through_put_revokes_tokens_and_reactivation_does_not_revive_them(): void
    {
        $coach = User::factory()->create();
        $coach->createToken('api');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/v1/users/{$coach->id}", $this->profile($coach, ['isActive' => false]))->assertOk();
        $this->assertFalse($coach->fresh()->is_active);
        $this->assertCount(0, $coach->fresh()->tokens);

        $this->putJson("/api/v1/users/{$coach->id}", $this->profile($coach, ['isActive' => true]))->assertOk();
        $this->assertTrue($coach->fresh()->is_active);
        $this->assertCount(0, $coach->fresh()->tokens);
    }

    public function test_username_cannot_contain_at_sign_or_spaces(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/users', $this->newUserPayload(['username' => 'bad@name']))->assertStatus(422);
        $this->postJson('/api/v1/users', $this->newUserPayload(['username' => 'bad name']))->assertStatus(422);
        $this->postJson('/api/v1/users', $this->newUserPayload(['username' => 'good_name']))->assertStatus(201);
    }

    public function test_change_password_is_rate_limited_and_keeps_retry_after(): void
    {
        Sanctum::actingAs(User::factory()->create(['password' => bcrypt('oldpassword')]));

        $payload = [
            'currentPassword' => 'wrong',
            'newPassword' => 'newpassword123',
            'newPassword_confirmation' => 'newpassword123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/change-password', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/change-password', $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
