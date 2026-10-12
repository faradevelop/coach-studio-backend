<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutProgramTemplateFlagTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Plan',
            'goal' => 'strength',
            'level' => 'beginner',
            'daysPerWeek' => 3,
        ], $overrides);
    }

    public function test_store_respects_explicit_false(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workout-programs', $this->payload(['isTemplate' => false]))
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', false);
    }

    public function test_store_respects_explicit_true(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workout-programs', $this->payload(['isTemplate' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', true);
    }

    public function test_store_without_flag_keeps_legacy_default_true(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workout-programs', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', true);
    }

    public function test_update_can_toggle_the_flag_both_ways(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['is_template' => true]);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/workout-programs/{$program->id}", $this->payload(['isTemplate' => false]))
            ->assertOk()
            ->assertJsonPath('data.isTemplate', false);
        $this->assertFalse($program->fresh()->is_template);

        $this->putJson("/api/v1/workout-programs/{$program->id}", $this->payload(['isTemplate' => true]))
            ->assertOk();
        $this->assertTrue($program->fresh()->is_template);
    }

    public function test_update_without_flag_keeps_current_value(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['is_template' => false]);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/workout-programs/{$program->id}", $this->payload())->assertOk();

        $this->assertFalse($program->fresh()->is_template);
    }

    public function test_non_boolean_flag_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workout-programs', $this->payload(['isTemplate' => 'maybe']))->assertStatus(422);
    }
}
