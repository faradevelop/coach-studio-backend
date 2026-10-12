<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AthleteProgramAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function programWithExercise(User $owner, string $title = 'Source'): WorkoutProgram
    {
        $program = WorkoutProgram::factory()->for($owner)->create([
            'title' => $title,
            'days_per_week' => 2,
            'notes' => 'original notes',
        ]);

        $pe = ProgramExercise::create([
            'workout_program_id' => $program->id,
            'day' => 1,
            'order_index' => 1,
            'sets' => '3',
            'rest' => '30',
            'training_system' => 'normal',
        ]);

        ProgramExerciseItem::create([
            'program_exercise_id' => $pe->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order_index' => 1,
            'reps' => ['10', '10', '8'],
            'tempo' => '-',
        ]);

        return $program;
    }

    private function assign(Athlete $athlete, WorkoutProgram $source, array $extra = [])
    {
        return $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", ['sourceProgramId' => $source->id] + $extra);
    }

    public function test_coach_can_assign_program_and_source_is_cloned_not_mutated(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        $before = $source->fresh()->only(['user_id', 'title', 'goal', 'level', 'days_per_week', 'notes', 'is_template']);
        Sanctum::actingAs($coach);

        $response = $this->assign($athlete, $source)
            ->assertStatus(201)
            ->assertJsonPath('data.athleteId', $athlete->id)
            ->assertJsonPath('data.sourceProgramId', $source->id)
            ->assertJsonPath('data.isCurrent', true)
            ->assertJsonPath('data.endedAt', null);

        $copyId = $response->json('data.programId');
        $this->assertNotSame($source->id, $copyId);

        // Source untouched and not linked to the athlete.
        $this->assertSame($before, $source->fresh()->only(array_keys($before)));
        $this->assertSame(0, AthleteProgramAssignment::where('workout_program_id', $source->id)->count());

        // The copy is an independent, athlete-specific, non-template program.
        $copy = WorkoutProgram::findOrFail($copyId);
        $this->assertSame($coach->id, $copy->user_id);
        $this->assertFalse($copy->is_template);
        $this->assertSame('Source', $copy->title);
        $this->assertSame(1, ProgramExercise::where('workout_program_id', $copy->id)->count());
        $this->assertSame(1, ProgramExerciseItem::whereIn(
            'program_exercise_id',
            ProgramExercise::where('workout_program_id', $copy->id)->pluck('id')
        )->count());

        // Editing the copy never touches the source.
        $copy->update(['title' => 'Changed']);
        $this->assertSame('Source', $source->fresh()->title);
    }

    public function test_title_can_be_overridden(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        Sanctum::actingAs($coach);

        $this->assign($athlete, $source, ['title' => 'Ali - Cut'])
            ->assertStatus(201)
            ->assertJsonPath('data.program.title', 'Ali - Cut');
    }

    public function test_admin_assignment_is_owned_by_the_athletes_coach(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        Sanctum::actingAs($admin);

        $response = $this->assign($athlete, $source)->assertStatus(201);

        $this->assertSame($coach->id, WorkoutProgram::findOrFail($response->json('data.programId'))->user_id);
        $this->assertSame($admin->id, AthleteProgramAssignment::firstOrFail()->assigned_by);
    }

    public function test_changing_program_ends_previous_assignment_and_keeps_one_current(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $a = $this->programWithExercise($coach, 'A');
        $b = $this->programWithExercise($coach, 'B');
        Sanctum::actingAs($coach);

        $first = $this->assign($athlete, $a)->assertStatus(201);
        $this->travel(10)->minutes();
        $second = $this->assign($athlete, $b)->assertStatus(201);

        $this->assertSame(2, AthleteProgramAssignment::where('athlete_id', $athlete->id)->count());
        $this->assertSame(1, AthleteProgramAssignment::where('athlete_id', $athlete->id)->whereNull('ended_at')->count());

        $this->assertNotNull(AthleteProgramAssignment::findOrFail($first->json('data.id'))->ended_at);
        $this->assertNull(AthleteProgramAssignment::findOrFail($second->json('data.id'))->ended_at);

        $this->getJson("/api/v1/athletes/{$athlete->id}")
            ->assertJsonPath('data.currentAssignment.id', $second->json('data.id'))
            ->assertJsonPath('data.currentAssignment.program.title', 'B');

        // Both sources survive untouched.
        $this->assertSame('A', $a->fresh()->title);
        $this->assertSame('B', $b->fresh()->title);
    }

    public function test_program_history_lists_current_first_then_previous(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $a = $this->programWithExercise($coach, 'A');
        $b = $this->programWithExercise($coach, 'B');
        Sanctum::actingAs($coach);

        $this->assign($athlete, $a)->assertStatus(201);
        $this->travel(10)->minutes();
        $this->assign($athlete, $b)->assertStatus(201);

        $this->getJson("/api/v1/athletes/{$athlete->id}/program-history")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.isCurrent', true)
            ->assertJsonPath('data.0.program.title', 'B')
            ->assertJsonPath('data.0.sourceProgram.id', $b->id)
            ->assertJsonPath('data.1.isCurrent', false)
            ->assertJsonPath('data.1.program.title', 'A')
            ->assertJsonPath('data.1.sourceProgram.id', $a->id)
            ->assertJsonPath('data.1.assignedBy', $coach->id);
    }

    public function test_archived_athlete_cannot_receive_a_program(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->archived()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        $programsBefore = WorkoutProgram::count();
        Sanctum::actingAs($coach);

        $this->assign($athlete, $source)->assertStatus(422);

        $this->assertSame(0, AthleteProgramAssignment::count());
        $this->assertSame($programsBefore, WorkoutProgram::count()); // no orphan clone
    }

    public function test_restoring_allows_assignment_again(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/athletes/{$athlete->id}/archive")->assertOk();
        $this->assign($athlete, $source)->assertStatus(422);
        $this->postJson("/api/v1/athletes/{$athlete->id}/restore")->assertOk();
        $this->assign($athlete, $source)->assertStatus(201);
    }

    public function test_coach_cannot_assign_to_another_coachs_athlete(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $owner->id]);
        $source = $this->programWithExercise($intruder);
        Sanctum::actingAs($intruder);

        $this->assign($athlete, $source)->assertStatus(404);
        $this->assertSame(0, AthleteProgramAssignment::count());
    }

    public function test_coach_cannot_assign_another_coachs_program(): void
    {
        $coach = User::factory()->create();
        $other = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $foreignProgram = $this->programWithExercise($other);
        Sanctum::actingAs($coach);

        $this->assign($athlete, $foreignProgram)->assertStatus(404);
        $this->assertSame(0, AthleteProgramAssignment::count());
    }

    public function test_assign_validation(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", [])->assertStatus(422);
        $this->postJson("/api/v1/athletes/{$athlete->id}/program-assignments", ['sourceProgramId' => 'nope'])->assertStatus(422);
    }

    public function test_athlete_copies_are_hidden_from_the_program_list_and_cannot_be_deleted(): void
    {
        $coach = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        $source = $this->programWithExercise($coach);
        Sanctum::actingAs($coach);

        $copyId = $this->assign($athlete, $source)->json('data.programId');

        $ids = array_column($this->getJson('/api/v1/workout-programs')->json('data'), 'id');
        $this->assertContains($source->id, $ids);
        $this->assertNotContains($copyId, $ids);

        $this->deleteJson("/api/v1/workout-programs/{$copyId}")->assertStatus(422);
        $this->assertDatabaseHas('workout_programs', ['id' => $copyId]);

        // The source stays deletable; the history keeps the copy and nulls source_program_id.
        $this->deleteJson("/api/v1/workout-programs/{$source->id}")->assertOk();
        $this->getJson("/api/v1/athletes/{$athlete->id}/program-history")
            ->assertJsonPath('data.0.sourceProgram', null)
            ->assertJsonPath('data.0.program.id', $copyId);
    }

    public function test_public_duplicate_behaviour_is_unchanged(): void
    {
        $coach = User::factory()->create();
        $source = $this->programWithExercise($coach, 'Plan');
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/workout-programs/{$source->id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Plan (Copy)')
            ->assertJsonPath('data.isTemplate', true);
    }
}
