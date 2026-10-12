<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutProgramShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_get_own_program(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['title' => 'Mine']);
        Sanctum::actingAs($coach);

        $this->getJson("/api/v1/workout-programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $program->id)
            ->assertJsonPath('data.title', 'Mine')
            ->assertJsonPath('data.athleteId', null);
    }

    public function test_coach_cannot_get_another_coachs_program(): void
    {
        $program = WorkoutProgram::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/workout-programs/{$program->id}")->assertStatus(404);
    }

    public function test_admin_can_get_any_program(): void
    {
        $program = WorkoutProgram::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/v1/workout-programs/{$program->id}")->assertOk();
    }

    public function test_missing_program_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/workout-programs/' . Str::uuid())->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $program = WorkoutProgram::factory()->create();

        $this->getJson("/api/v1/workout-programs/{$program->id}")->assertStatus(401);
    }

    public function test_athlete_copy_is_reachable_by_id_and_exposes_athlete_id(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = WorkoutProgram::factory()->for($coach)->create();
        Sanctum::actingAs($coach);

        $copyId = $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", ['sourceProgramId' => $source->id])
            ->json('data.programId');

        $this->getJson("/api/v1/workout-programs/{$copyId}")
            ->assertOk()
            ->assertJsonPath('data.athleteId', $athlete->id);
    }
}
