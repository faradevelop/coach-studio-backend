<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function exercisePayload(array $overrides = []): array
    {
        $muscle = Muscle::firstOrCreate(['slug' => 'chest'], ['name' => 'chest']);

        return array_merge([
            'name' => 'Bench Press', 'type' => 'strength', 'difficulty' => 'beginner',
            'equipment' => 'barbell', 'muscleSlugs' => [$muscle->slug],
        ], $overrides);
    }

    private function peModel(WorkoutProgram $program, int $day, Exercise $exercise): ProgramExercise
    {
        $pe = ProgramExercise::create([
            'workout_program_id' => $program->id, 'day' => $day, 'order_index' => 1,
            'sets' => '3', 'rest' => '30', 'training_system' => 'normal',
        ]);
        ProgramExerciseItem::create([
            'program_exercise_id' => $pe->id, 'exercise_id' => $exercise->id,
            'order_index' => 1, 'reps' => ['10'], 'tempo' => '-',
        ]);

        return $pe;
    }

    private function pePayload(WorkoutProgram $program, int $day, Exercise $exercise, array $overrides = []): array
    {
        return array_merge([
            'workoutId' => $program->id, 'day' => $day, 'sets' => '3', 'rest' => '30',
            'trainingSystem' => 'normal',
            'items' => [['exerciseId' => $exercise->id, 'reps' => ['10'], 'tempo' => '-']],
        ], $overrides);
    }

    // ---------------------------------------------------------- exercises

    public function test_exercise_rejects_enum_values_flutter_cannot_parse(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach ([['difficulty' => 'Beginner'], ['difficulty' => 'expert'], ['equipment' => 'Barbell'], ['equipment' => 'kettlebell']] as $bad) {
            $this->postJson('/api/v1/exercises', $this->exercisePayload($bad))->assertStatus(422);
        }

        $this->postJson('/api/v1/exercises', $this->exercisePayload())->assertStatus(201);
    }

    public function test_exercise_name_letters_are_normalized_but_digits_are_not(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', $this->exercisePayload(['name' => 'پرس سينه ۴۵']))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'پرس سینه ۴۵');
    }

    public function test_search_treats_persian_and_latin_digits_as_equal(): void
    {
        Exercise::factory()->create(['name' => 'پرس ۴۵']);
        Exercise::factory()->create(['name' => 'Incline 45']);
        Sanctum::actingAs(User::factory()->create());

        foreach (['۴۵', '45'] as $term) {
            $names = array_column($this->getJson('/api/v1/exercises?search=' . urlencode($term))->assertOk()->json('data'), 'name');
            sort($names);
            $this->assertSame(['Incline 45', 'پرس ۴۵'], $names, "term {$term}");
        }
    }

    public function test_exercise_text_and_url_limits(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', $this->exercisePayload(['description' => str_repeat('a', 5001)]))->assertStatus(422);
        $this->postJson('/api/v1/exercises', $this->exercisePayload(['imageUrl' => 'javascript:alert(1)']))->assertStatus(422);
        $this->postJson('/api/v1/exercises', $this->exercisePayload(['imageUrl' => 'https://example.com/a.png', 'videoUrl' => '']))->assertStatus(201);
    }

    // ----------------------------------------------------------- programs

    public function test_days_per_week_must_be_between_1_and_7(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = ['title' => 'P', 'goal' => 'strength', 'level' => 'beginner'];

        $this->postJson('/api/v1/workout-programs', $payload + ['daysPerWeek' => 8])->assertStatus(422);
        $this->postJson('/api/v1/workout-programs', $payload + ['daysPerWeek' => 0])->assertStatus(422);
        $this->postJson('/api/v1/workout-programs', $payload + ['daysPerWeek' => 7])->assertStatus(201);
    }

    public function test_lowering_days_below_a_day_with_exercises_is_rejected(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => 3]);
        $this->peModel($program, 3, Exercise::factory()->create());
        Sanctum::actingAs($coach);

        $payload = ['title' => 'P', 'goal' => 'strength', 'level' => 'beginner'];

        $this->putJson("/api/v1/workout-programs/{$program->id}", $payload + ['daysPerWeek' => 2])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['daysPerWeek']]);
        $this->assertSame(3, $program->fresh()->days_per_week);
        $this->assertSame(1, ProgramExercise::count()); // nothing deleted

        $this->putJson("/api/v1/workout-programs/{$program->id}", $payload + ['daysPerWeek' => 3])->assertOk();
    }

    public function test_duplicate_title_never_exceeds_255_characters(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['title' => str_repeat('a', 255)]);
        Sanctum::actingAs($coach);

        $title = $this->postJson("/api/v1/workout-programs/{$program->id}/duplicate")
            ->assertStatus(201)->json('data.title');

        $this->assertLessThanOrEqual(255, mb_strlen($title));
        $this->assertStringEndsWith(' (Copy)', $title);
    }

    // ----------------------------------------------------- program exercises

    public function test_program_exercise_day_must_exist_in_the_program(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => 3]);
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/program-exercises', $this->pePayload($program, 4, $exercise))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['day']]);
        $this->postJson('/api/v1/program-exercises', $this->pePayload($program, 300, $exercise))->assertStatus(422); // not 500
        $this->postJson('/api/v1/program-exercises', $this->pePayload($program, 3, $exercise))->assertStatus(201);
    }

    public function test_moving_a_program_exercise_to_a_missing_day_is_rejected(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => 3]);
        $exercise = Exercise::factory()->create();
        $pe = $this->peModel($program, 1, $exercise);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/program-exercises/{$pe->id}", $this->pePayload($program, 5, $exercise))->assertStatus(422);
        $this->assertSame(1, $pe->fresh()->day);
    }

    public function test_non_array_items_return_422_not_500(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => 3]);
        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/program-exercises', [
            'workoutId' => $program->id, 'day' => 1, 'sets' => '3', 'rest' => '30',
            'trainingSystem' => 'normal', 'items' => 'abc',
        ])->assertStatus(422);
    }

    public function test_inactive_exercise_cannot_be_added_but_an_existing_one_can_be_kept(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create(['days_per_week' => 3]);
        $kept = Exercise::factory()->create();
        $pe = $this->peModel($program, 1, $kept);
        $kept->update(['is_active' => false]); // deactivated AFTER it was added
        $other = Exercise::factory()->create(['is_active' => false]);
        Sanctum::actingAs($coach);

        // New inactive exercise: rejected on create...
        $this->postJson('/api/v1/program-exercises', $this->pePayload($program, 1, $other))->assertStatus(422);
        // ...and on update.
        $this->putJson("/api/v1/program-exercises/{$pe->id}", $this->pePayload($program, 1, $other))->assertStatus(422);
        // The already-present inactive exercise can still be edited.
        $this->putJson("/api/v1/program-exercises/{$pe->id}", $this->pePayload($program, 1, $kept, ['sets' => '4']))->assertOk();
        $this->assertSame('4', $pe->fresh()->sets);
    }
}
