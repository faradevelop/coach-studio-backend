<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_coach(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/users', [
            'username' => 'newcoach',
            'email' => 'newcoach@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'coach',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['username' => 'newcoach', 'role' => 'coach']);
    }

    public function test_admin_can_create_another_admin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/users', [
            'username' => 'newadmin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertStatus(201);
    }

    public function test_admin_can_deactivate_a_coach(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $coach = User::factory()->create();

        $this->deleteJson("/api/v1/users/{$coach->id}")->assertOk();
        $this->assertFalse($coach->fresh()->is_active);
    }

    public function test_deactivated_coach_cannot_log_in(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $coach = User::factory()->create(['username' => 'tobedeactivated', 'password' => bcrypt('secret123')]);

        $this->deleteJson("/api/v1/users/{$coach->id}")->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'tobedeactivated',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_deactivated_coachs_tokens_are_revoked(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $coach = User::factory()->create();
        $coach->createToken('api');

        $this->deleteJson("/api/v1/users/{$coach->id}")->assertOk();
        $this->assertCount(0, $coach->fresh()->tokens);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/users/{$admin->id}", [
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'coach',
        ])->assertStatus(422);

        $this->assertEquals('admin', $admin->fresh()->role);
    }

    /**
     * Note: given consistent role semantics, a *different active* admin
     * attempting this on the last remaining admin is a contradiction (if
     * they're active and admin, they themselves count, so the target can't
     * be "the last one"). That path is only reachable via self-action,
     * already covered above. These two tests exercise the count-based
     * invariant directly at the service layer instead, simulating the
     * only way this branch is actually reachable.
     */
    public function test_last_active_admin_cannot_be_deactivated(): void
    {
        $onlyAdmin = User::factory()->admin()->create();
        $actor = User::factory()->admin()->create();
        User::where('id', $actor->id)->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(UserService::class)->deactivate($actor->fresh(), $onlyAdmin);
    }

    public function test_last_active_admin_cannot_be_demoted(): void
    {
        $onlyAdmin = User::factory()->admin()->create();
        $actor = User::factory()->admin()->create();
        User::where('id', $actor->id)->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(UserService::class)->update($actor->fresh(), $onlyAdmin, [
            'username' => $onlyAdmin->username,
            'email' => $onlyAdmin->email,
            'role' => 'coach',
        ]);
    }
}
