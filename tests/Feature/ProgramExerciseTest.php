<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\User;
use App\Models\WorkoutProgram;
use Database\Seeders\MuscleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramExerciseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: WorkoutProgram} */
    private function coachWithProgram(int $days = 3): array
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => $days]);
        Sanctum::actingAs($coach);

        return [$coach, $program];
    }

    /** @param array<int,string> $exerciseIds */
    private function payload(WorkoutProgram $program, int $day, array $exerciseIds, string $rest = '30'): array
    {
        return [
            'workoutId' => $program->id,
            'day' => $day,
            'sets' => '3',
            'rest' => $rest,
            'trainingSystem' => count($exerciseIds) === 2 ? 'superSet' : 'normal',
            'items' => array_map(fn($id) => [
                'exerciseId' => $id,
                'reps' => ['10', '8'],
                'tempo' => '-',
                'description' => null,
            ], $exerciseIds),
        ];
    }

    /** `rest` doubles as a label so rows are easy to identify after reordering. */
    private function add(WorkoutProgram $program, int $day, string $label, ?Exercise $exercise = null): string
    {
        $exercise ??= Exercise::factory()->create();

        return $this->postJson('/api/v1/program-exercises', $this->payload($program, $day, [$exercise->id], $label))
            ->assertStatus(201)
            ->json('data.id');
    }

    /** @return array<int,string> labels of a day, in order */
    private function labels(WorkoutProgram $program, int $day): array
    {
        return ProgramExercise::where('workout_program_id', $program->id)
            ->where('day', $day)
            ->orderBy('order_index')
            ->pluck('rest')
            ->all();
    }

    private function orders(WorkoutProgram $program, int $day): array
    {
        return ProgramExercise::where('workout_program_id', $program->id)
            ->where('day', $day)
            ->orderBy('order_index')
            ->pluck('order_index')
            ->all();
    }

    public function test_create_appends_in_order_and_each_day_has_its_own_sequence(): void
    {
        [, $program] = $this->coachWithProgram();

        $this->add($program, 1, 'A');
        $this->add($program, 1, 'B');
        $this->add($program, 2, 'C');

        $this->assertSame([1, 2], $this->orders($program, 1));
        $this->assertSame([1], $this->orders($program, 2));
        $this->assertSame(['A', 'B'], $this->labels($program, 1));
    }

    public function test_normal_block_stores_reps_tempo_and_item_order(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create();

        $this->postJson('/api/v1/program-exercises', $this->payload($program, 1, [$exercise->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.trainingSystem', 'normal')
            ->assertJsonPath('data.items.0.reps', ['10', '8'])
            ->assertJsonPath('data.items.0.order', 1);
    }

    public function test_super_set_needs_exactly_two_items_and_normal_exactly_one(): void
    {
        [, $program] = $this->coachWithProgram();
        $a = Exercise::factory()->create();
        $b = Exercise::factory()->create();

        $this->postJson('/api/v1/program-exercises', $this->payload($program, 1, [$a->id, $b->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.trainingSystem', 'superSet')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.order', 2);

        $superSetWithOne = $this->payload($program, 1, [$a->id]);
        $superSetWithOne['trainingSystem'] = 'superSet';
        $this->postJson('/api/v1/program-exercises', $superSetWithOne)
            ->assertStatus(422)->assertJsonStructure(['errors' => ['items']]);

        $normalWithTwo = $this->payload($program, 1, [$a->id, $b->id]);
        $normalWithTwo['trainingSystem'] = 'normal';
        $this->postJson('/api/v1/program-exercises', $normalWithTwo)
            ->assertStatus(422)->assertJsonStructure(['errors' => ['items']]);

        $this->assertSame(1, ProgramExercise::count()); // only the valid superset was saved
    }

    public function test_reorder_moves_a_block_and_clamps_the_target(): void
    {
        [, $program] = $this->coachWithProgram();
        $this->add($program, 1, 'A');
        $this->add($program, 1, 'B');
        $c = $this->add($program, 1, 'C');

        $this->patchJson("/api/v1/program-exercises/{$c}/reorder", ['order' => 1])->assertOk();
        $this->assertSame(['C', 'A', 'B'], $this->labels($program, 1));
        $this->assertSame([1, 2, 3], $this->orders($program, 1));

        $a = ProgramExercise::where('rest', 'A')->value('id');
        $this->patchJson("/api/v1/program-exercises/{$a}/reorder", ['order' => 99])->assertOk();
        $this->assertSame(['C', 'B', 'A'], $this->labels($program, 1));

        $this->patchJson("/api/v1/program-exercises/{$a}/reorder", ['order' => 0])->assertStatus(422);
    }

    public function test_delete_renumbers_the_day_and_removes_items(): void
    {
        [, $program] = $this->coachWithProgram();
        $this->add($program, 1, 'A');
        $b = $this->add($program, 1, 'B');
        $this->add($program, 1, 'C');

        $this->deleteJson("/api/v1/program-exercises/{$b}")->assertOk();

        $this->assertSame(['A', 'C'], $this->labels($program, 1));
        $this->assertSame([1, 2], $this->orders($program, 1));
        $this->assertSame(0, ProgramExerciseItem::where('program_exercise_id', $b)->count());
    }

    public function test_update_replaces_items_and_keeps_its_position(): void
    {
        [, $program] = $this->coachWithProgram();
        $first = Exercise::factory()->create();
        $second = Exercise::factory()->create();
        $id = $this->add($program, 1, 'A', $first);
        $this->add($program, 1, 'B');

        $payload = $this->payload($program, 1, [$second->id], 'A2');
        $payload['sets'] = '5';

        $this->putJson("/api/v1/program-exercises/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.sets', '5')
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.items.0.exerciseId', $second->id);

        $this->assertSame(['A2', 'B'], $this->labels($program, 1));
        $this->assertSame(1, ProgramExerciseItem::where('program_exercise_id', $id)->count());
    }

    public function test_changing_day_moves_the_block_and_renumbers_both_days(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create();
        $a = $this->add($program, 1, 'A', $exercise);
        $this->add($program, 1, 'B');
        $this->add($program, 2, 'C');

        $this->putJson("/api/v1/program-exercises/{$a}", $this->payload($program, 2, [$exercise->id], 'A'))
            ->assertOk()
            ->assertJsonPath('data.day', 2);

        $this->assertSame(['B'], $this->labels($program, 1));
        $this->assertSame([1], $this->orders($program, 1));
        $this->assertSame(['C', 'A'], $this->labels($program, 2));
        $this->assertSame([1, 2], $this->orders($program, 2));
    }

    public function test_index_returns_blocks_with_their_exercises_sorted_by_day_then_order(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create(['name' => 'Squat']);
        $this->add($program, 2, 'D2');
        $this->add($program, 1, 'D1', $exercise);

        $this->getJson("/api/v1/workout-programs/{$program->id}/program-exercises")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.programExercise.rest', 'D1')
            ->assertJsonPath('data.0.programExercise.day', 1)
            ->assertJsonPath('data.0.items.0.exercise.id', $exercise->id)
            ->assertJsonPath('data.0.items.0.exercise.name', 'Squat')
            ->assertJsonPath('data.1.programExercise.rest', 'D2');
    }

    public function test_another_coach_gets_404_everywhere(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create();
        $id = $this->add($program, 1, 'A', $exercise);

        Sanctum::actingAs(User::factory()->create()); // intruder

        $this->getJson("/api/v1/workout-programs/{$program->id}/program-exercises")->assertStatus(404);
        $this->postJson('/api/v1/program-exercises', $this->payload($program, 1, [$exercise->id]))->assertStatus(404);
        $this->putJson("/api/v1/program-exercises/{$id}", $this->payload($program, 1, [$exercise->id], 'X'))->assertStatus(404);
        $this->patchJson("/api/v1/program-exercises/{$id}/reorder", ['order' => 1])->assertStatus(404);
        $this->deleteJson("/api/v1/program-exercises/{$id}")->assertStatus(404);

        $this->assertSame(['A'], $this->labels($program, 1)); // untouched
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $program = WorkoutProgram::factory()->create();

        $this->getJson("/api/v1/workout-programs/{$program->id}/program-exercises")->assertStatus(401);
        $this->postJson('/api/v1/program-exercises', [])->assertStatus(401);
    }

    public function test_duplicating_a_program_copies_blocks_and_items_independently(): void
    {
        [, $program] = $this->coachWithProgram();
        $a = Exercise::factory()->create();
        $b = Exercise::factory()->create();
        $this->postJson('/api/v1/program-exercises', $this->payload($program, 1, [$a->id, $b->id], 'SS'))->assertStatus(201);
        $this->add($program, 2, 'N', $a);

        $copyId = $this->postJson("/api/v1/workout-programs/{$program->id}/duplicate")
            ->assertStatus(201)->json('data.id');

        $this->assertNotSame($program->id, $copyId);
        $this->assertSame(2, ProgramExercise::where('workout_program_id', $copyId)->count());

        $copySuperSet = ProgramExercise::where('workout_program_id', $copyId)->where('rest', 'SS')->firstOrFail();
        $this->assertSame(
            [$a->id, $b->id],
            $copySuperSet->items->pluck('exercise_id')->all()
        );

        // Editing the copy never touches the original.
        $this->deleteJson("/api/v1/program-exercises/{$copySuperSet->id}")->assertOk();
        $this->assertSame(['SS'], $this->labels($program, 1));
    }

    public function test_moving_the_first_block_to_an_empty_day_leaves_no_gaps(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create();
        $a = $this->add($program, 1, 'A', $exercise);
        $this->add($program, 1, 'B');

        $this->putJson("/api/v1/program-exercises/{$a}", $this->payload($program, 3, [$exercise->id], 'A'))
            ->assertOk()
            ->assertJsonPath('data.day', 3)
            ->assertJsonPath('data.order', 1);

        $this->assertSame(['B'], $this->labels($program, 1));
        $this->assertSame([1], $this->orders($program, 1));
        $this->assertSame(['A'], $this->labels($program, 3));
        $this->assertSame([1], $this->orders($program, 3));
    }

    public function test_moving_a_middle_block_closes_the_gap_in_the_source_day(): void
    {
        [, $program] = $this->coachWithProgram();
        $exercise = Exercise::factory()->create();
        $this->add($program, 1, 'A');
        $b = $this->add($program, 1, 'B', $exercise);
        $this->add($program, 1, 'C');

        $this->putJson("/api/v1/program-exercises/{$b}", $this->payload($program, 2, [$exercise->id], 'B'))
            ->assertOk();

        $this->assertSame(['A', 'C'], $this->labels($program, 1));
        $this->assertSame([1, 2], $this->orders($program, 1));
        $this->assertSame(['B'], $this->labels($program, 2));
    }

    // ---------------------------------------------------------- muscles

    public function test_muscles_are_listed_for_authenticated_users_only(): void
    {
        $this->getJson('/api/v1/muscles')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        Muscle::factory()->count(3)->create();

        $this->getJson('/api/v1/muscles')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_muscle_seeder_is_idempotent(): void
    {
        $this->seed(MuscleSeeder::class);
        $count = Muscle::count();
        $this->seed(MuscleSeeder::class);

        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, Muscle::count());
    }
}
