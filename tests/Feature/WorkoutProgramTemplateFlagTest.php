<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
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
            'title' => 'Plan', 'goal' => 'strength', 'level' => 'beginner', 'daysPerWeek' => 3,
        ], $overrides);
    }

    public function test_store_always_creates_a_draft_even_if_client_sends_false(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workout-programs', $this->payload(['isTemplate' => false]))
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', true)
            ->assertJsonPath('data.status', 'template');

        $this->postJson('/api/v1/workout-programs', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', true);
    }

    public function test_update_cannot_change_the_template_flag(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['is_template' => true]);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/workout-programs/{$program->id}", $this->payload(['isTemplate' => false]))
            ->assertOk()
            ->assertJsonPath('data.isTemplate', true);

        $this->assertTrue($program->fresh()->is_template);
    }

    public function test_list_contains_drafts_and_assigned_copies_with_status_and_athlete(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'Ali', 'last_name' => 'Rezaei']);
        $source = WorkoutProgram::factory()->for($coach)->create();
        Sanctum::actingAs($coach);

        $copyId = $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", ['sourceProgramId' => $source->id])
            ->json('data.programId');

        $list = collect($this->getJson('/api/v1/workout-programs')->assertOk()->json('data'))->keyBy('id');

        $this->assertCount(2, $list);
        $this->assertSame('template', $list[$source->id]['status']);
        $this->assertNull($list[$source->id]['athlete']);
        $this->assertSame('assigned', $list[$copyId]['status']);
        $this->assertFalse($list[$copyId]['isTemplate']);
        $this->assertSame($athlete->id, $list[$copyId]['athlete']['id']);
        $this->assertSame('Ali Rezaei', $list[$copyId]['athlete']['fullName']);
        $this->assertTrue($list[$copyId]['assignment']['isCurrent']);
    }

    public function test_duplicating_an_athlete_copy_creates_an_independent_draft(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = WorkoutProgram::factory()->for($coach)->create();
        Sanctum::actingAs($coach);

        $copyId = $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", ['sourceProgramId' => $source->id])
            ->json('data.programId');

        $dupId = $this->postJson("/api/v1/workout-programs/{$copyId}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('data.isTemplate', true)
            ->assertJsonPath('data.status', 'template')
            ->json('data.id');

        $this->assertSame(0, AthleteProgramAssignment::where('workout_program_id', $dupId)->count());
        $this->assertSame(1, AthleteProgramAssignment::where('athlete_id', $athlete->id)->count());
    }

    public function test_assigning_one_athletes_program_to_another_clones_it_and_keeps_history(): void
    {
        $coach = User::factory()->create();
        $a = Athlete::factory()->create(['coach_id' => $coach->id]);
        $b = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = WorkoutProgram::factory()->for($coach)->create();
        Sanctum::actingAs($coach);

        $aCopyId = $this->postJson("/api/v1/athletes/{$a->id}/program-assignments", ['sourceProgramId' => $source->id])
            ->json('data.programId');

        $bAssignment = $this->postJson("/api/v1/athletes/{$b->id}/program-assignments", ['sourceProgramId' => $aCopyId])
            ->assertStatus(201)
            ->assertJsonPath('data.sourceProgramId', $aCopyId);

        $bCopyId = $bAssignment->json('data.programId');
        $this->assertNotSame($aCopyId, $bCopyId);
        $this->assertFalse(WorkoutProgram::findOrFail($bCopyId)->is_template);

        // A's assignment is untouched and still current.
        $aAssignment = AthleteProgramAssignment::where('athlete_id', $a->id)->firstOrFail();
        $this->assertSame($aCopyId, $aAssignment->workout_program_id);
        $this->assertNull($aAssignment->ended_at);
    }
}
