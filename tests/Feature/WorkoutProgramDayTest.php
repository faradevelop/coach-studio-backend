<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutProgramDayTest extends TestCase
{
    use RefreshDatabase;

    private function program(User $owner, int $days = 3): WorkoutProgram
    {
        return WorkoutProgram::factory()->for($owner)->create(['days_per_week' => $days]);
    }

    /** $label is stored in `rest` so rows can be identified after renumbering. */
    private function pe(WorkoutProgram $program, int $day, int $order, string $label): ProgramExercise
    {
        return ProgramExercise::create([
            'workout_program_id' => $program->id,
            'day' => $day,
            'order_index' => $order,
            'sets' => '3',
            'rest' => $label,
            'training_system' => 'normal',
        ]);
    }

    private function locate(string $label): ProgramExercise
    {
        return ProgramExercise::where('rest', $label)->firstOrFail();
    }

    public function test_add_day_increments_days_per_week(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 3);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/workout-programs/{$program->id}/days")
            ->assertStatus(201)
            ->assertJsonPath('data.daysPerWeek', 4);

        $this->assertSame(4, $program->fresh()->days_per_week);
    }

    public function test_add_day_is_rejected_past_the_maximum(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 7);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/workout-programs/{$program->id}/days")->assertStatus(422);
        $this->assertSame(7, $program->fresh()->days_per_week);
    }

    public function test_delete_day_removes_its_exercises_and_shifts_later_days(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 4);
        $this->pe($program, 1, 1, 'D1');
        $doomed = $this->pe($program, 2, 1, 'D2a');
        $this->pe($program, 2, 2, 'D2b');
        $this->pe($program, 3, 1, 'D3');
        $this->pe($program, 4, 1, 'D4a');
        $this->pe($program, 4, 2, 'D4b');

        ProgramExerciseItem::create([
            'program_exercise_id' => $doomed->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order_index' => 1,
            'reps' => ['10'],
            'tempo' => '-',
        ]);

        Sanctum::actingAs($coach);

        $this->deleteJson("/api/v1/workout-programs/{$program->id}/days/2")
            ->assertOk()
            ->assertJsonPath('data.daysPerWeek', 3);

        $this->assertSame(3, $program->fresh()->days_per_week);
        $this->assertDatabaseMissing('program_exercises', ['rest' => 'D2a']);
        $this->assertDatabaseMissing('program_exercises', ['rest' => 'D2b']);
        $this->assertSame(0, ProgramExerciseItem::count());

        $this->assertSame(1, $this->locate('D1')->day);
        $this->assertSame(2, $this->locate('D3')->day);
        $this->assertSame(3, $this->locate('D4a')->day);
        $this->assertSame(3, $this->locate('D4b')->day);
        // order within the day is preserved
        $this->assertSame(1, $this->locate('D4a')->order_index);
        $this->assertSame(2, $this->locate('D4b')->order_index);
    }

    public function test_delete_last_day_needs_no_shift(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 2);
        $this->pe($program, 1, 1, 'D1');
        $this->pe($program, 2, 1, 'D2');
        Sanctum::actingAs($coach);

        $this->deleteJson("/api/v1/workout-programs/{$program->id}/days/2")->assertOk();

        $this->assertSame(1, $program->fresh()->days_per_week);
        $this->assertSame(1, $this->locate('D1')->day);
        $this->assertDatabaseMissing('program_exercises', ['rest' => 'D2']);
    }

    public function test_cannot_delete_the_only_day(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 1);
        $this->pe($program, 1, 1, 'D1');
        Sanctum::actingAs($coach);

        $this->deleteJson("/api/v1/workout-programs/{$program->id}/days/1")->assertStatus(422);

        $this->assertSame(1, $program->fresh()->days_per_week);
        $this->assertDatabaseHas('program_exercises', ['rest' => 'D1']);
    }

    public function test_delete_nonexistent_day_returns_404(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 3);
        Sanctum::actingAs($coach);

        $this->deleteJson("/api/v1/workout-programs/{$program->id}/days/9")->assertStatus(404);
    }

    public function test_reorder_day_down_updates_all_exercise_days(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 3);
        $this->pe($program, 1, 1, 'D1a');
        $this->pe($program, 1, 2, 'D1b');
        $this->pe($program, 2, 1, 'D2');
        $this->pe($program, 3, 1, 'D3');
        Sanctum::actingAs($coach);

        // Move day 1 to position 3 -> old 2 becomes 1, old 3 becomes 2, old 1 becomes 3.
        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/1/reorder", ['order' => 3])
            ->assertOk()
            ->assertJsonPath('data.daysPerWeek', 3);

        $this->assertSame(3, $this->locate('D1a')->day);
        $this->assertSame(3, $this->locate('D1b')->day);
        $this->assertSame(1, $this->locate('D1a')->order_index);
        $this->assertSame(2, $this->locate('D1b')->order_index);
        $this->assertSame(1, $this->locate('D2')->day);
        $this->assertSame(2, $this->locate('D3')->day);
    }

    public function test_reorder_day_up_updates_all_exercise_days(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 3);
        $this->pe($program, 1, 1, 'D1');
        $this->pe($program, 2, 1, 'D2');
        $this->pe($program, 3, 1, 'D3');
        Sanctum::actingAs($coach);

        // Move day 3 to position 1 -> old 1 becomes 2, old 2 becomes 3.
        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/3/reorder", ['order' => 1])->assertOk();

        $this->assertSame(1, $this->locate('D3')->day);
        $this->assertSame(2, $this->locate('D1')->day);
        $this->assertSame(3, $this->locate('D2')->day);
    }

    public function test_reorder_swapping_adjacent_days_does_not_violate_unique_constraint(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 2);
        $this->pe($program, 1, 1, 'D1');
        $this->pe($program, 2, 1, 'D2'); // same order_index on purpose
        Sanctum::actingAs($coach);

        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/1/reorder", ['order' => 2])->assertOk();

        $this->assertSame(2, $this->locate('D1')->day);
        $this->assertSame(1, $this->locate('D2')->day);
    }

    public function test_reorder_rejects_out_of_range_target(): void
    {
        $coach = User::factory()->create();
        $program = $this->program($coach, 3);
        Sanctum::actingAs($coach);

        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/1/reorder", ['order' => 5])->assertStatus(422);
        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/1/reorder", [])->assertStatus(422);
    }

    public function test_coach_cannot_modify_days_of_another_coachs_program(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $program = $this->program($owner, 3);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/workout-programs/{$program->id}/days")->assertStatus(404);
        $this->deleteJson("/api/v1/workout-programs/{$program->id}/days/1")->assertStatus(404);
        $this->patchJson("/api/v1/workout-programs/{$program->id}/days/1/reorder", ['order' => 2])->assertStatus(404);

        $this->assertSame(3, $program->fresh()->days_per_week);
    }

    public function test_admin_can_modify_days_of_any_program(): void
    {
        $owner = User::factory()->create();
        $program = $this->program($owner, 3);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/v1/workout-programs/{$program->id}/days")->assertStatus(201);
        $this->assertSame(4, $program->fresh()->days_per_week);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $program = $this->program(User::factory()->create(), 3);

        $this->postJson("/api/v1/workout-programs/{$program->id}/days")->assertStatus(401);
    }
}
