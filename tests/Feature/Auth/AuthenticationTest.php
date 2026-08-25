<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'coach1', 'password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'coach1',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_email(): void
    {
        $user = User::factory()->create(['email' => 'coach2@example.com', 'password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'coach2@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create(['username' => 'coach3', 'password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'coach3',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_nonexistent_identifier(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ghost',
            'password' => 'whatever123',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'inactive1', 'password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'inactive1',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_logout_invalidates_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpassword')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'currentPassword' => 'oldpassword',
            'newPassword' => 'newpassword123',
            'newPassword_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_change_password_fails_with_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpassword')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'currentPassword' => 'wrong',
            'newPassword' => 'newpassword123',
            'newPassword_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }

    public function test_password_change_invalidates_all_existing_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpassword')]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/change-password', [
            'currentPassword' => 'oldpassword',
            'newPassword' => 'newpassword123',
            'newPassword_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
