<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExerciseCrudTest extends TestCase
{
    use RefreshDatabase;

    private function muscle(string $slug): Muscle
    {
        $group = MuscleGroup::firstOrCreate(['slug' => 'test-group'], ['name' => 'test', 'sort_order' => 1]);

        return Muscle::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'muscle_group_id' => $group->id, 'sort_order' => 1]
        );
    }

    private function payload(array $primary, array $secondary = [], array $overrides = []): array
    {
        foreach (array_merge($primary, $secondary) as $s) { $this->muscle($s); }

        $base = [
            'name' => 'Bench Press', 'type' => 'strength', 'difficulty' => 'beginner',
            'equipment' => 'barbell', 'primaryMuscleSlugs' => $primary,
        ];
        if ($secondary !== []) {
            $base['secondaryMuscleSlugs'] = $secondary;
        }

        return array_merge($base, $overrides);
    }

    private function exercise(array $attrs, array $primary, array $secondary = []): Exercise
    {
        $e = Exercise::factory()->create($attrs);
        $payload = [];
        foreach ($primary as $s) { $payload[$this->muscle($s)->id] = ['role' => 'primary']; }
        foreach ($secondary as $s) { $payload[$this->muscle($s)->id] = ['role' => 'secondary']; }
        $e->muscles()->sync($payload);

        return $e;
    }

    private function slugs(Exercise $e, string $role): array
    {
        return $e->fresh()->muscles
            ->filter(fn ($m) => $m->pivot->role === $role)
            ->pluck('slug')->sort()->values()->all();
    }

    private function names(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/exercises' . $query)->assertOk()->json('data'))
            ->pluck('name')->sort()->values()->all();
    }

    private function seedCatalog(): void
    {
        $this->exercise(['name' => 'Bench Press', 'type' => 'strength', 'difficulty' => 'beginner', 'equipment' => 'barbell'], ['chest'], ['triceps']);
        $this->exercise(['name' => 'Incline Bench', 'type' => 'strength', 'difficulty' => 'advanced', 'equipment' => 'dumbbell'], ['chest']);
        $this->exercise(['name' => 'Running', 'type' => 'cardio', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'description' => 'not a bench'], ['quadriceps']);
        $this->exercise(['name' => 'Squat', 'type' => 'strength', 'difficulty' => 'beginner', 'equipment' => 'barbell'], ['quadriceps', 'glutes']);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_create_with_primary_and_secondary_muscles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/v1/exercises', $this->payload(['chest', 'shoulders'], ['triceps']))
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.primaryMuscles')
            ->assertJsonCount(1, 'data.secondaryMuscles')
            ->json('data.id');

        $e = Exercise::findOrFail($id);
        $this->assertSame(['chest', 'shoulders'], $this->slugs($e, 'primary'));
        $this->assertSame(['triceps'], $this->slugs($e, 'secondary'));
    }

    public function test_secondary_muscles_are_optional(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', $this->payload(['chest']))
            ->assertStatus(201)
            ->assertJsonCount(1, 'data.primaryMuscles')
            ->assertJsonCount(0, 'data.secondaryMuscles');

        $this->postJson('/api/v1/exercises', $this->payload(['chest'], [], ['secondaryMuscleSlugs' => null]))
            ->assertStatus(201);
        $this->postJson('/api/v1/exercises', $this->payload(['chest'], [], ['secondaryMuscleSlugs' => []]))
            ->assertStatus(201);
    }

    public function test_primary_muscles_are_required(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->muscle('triceps');

        $noPrimary = $this->payload(['chest']);
        unset($noPrimary['primaryMuscleSlugs']);

        $this->postJson('/api/v1/exercises', $noPrimary)->assertStatus(422);
        $this->postJson('/api/v1/exercises', $this->payload(['chest'], [], ['primaryMuscleSlugs' => []]))->assertStatus(422);
        $this->postJson('/api/v1/exercises', array_merge($noPrimary, ['secondaryMuscleSlugs' => ['triceps']]))->assertStatus(422);
    }

    public function test_a_muscle_cannot_be_both_primary_and_secondary(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', $this->payload(['chest'], ['chest']))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['secondaryMuscleSlugs']]);

        $this->assertSame(0, Exercise::count());
    }

    public function test_unknown_and_duplicate_slugs_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', $this->payload(['chest'], [], ['secondaryMuscleSlugs' => ['ghost']]))
            ->assertStatus(422);
        $this->postJson('/api/v1/exercises', $this->payload(['chest', 'chest']))
            ->assertStatus(422);
    }

    #[DataProvider('muscleSets')]
    public function test_update_synchronizes_muscles(array $before, array $after): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $exercise = $this->exercise(['name' => 'X'], $before[0], $before[1]);

        $this->putJson("/api/v1/exercises/{$exercise->id}", $this->payload($after[0], $after[1], ['name' => 'X']))->assertOk();

        sort($after[0]);
        sort($after[1]);
        $this->assertSame($after[0], $this->slugs($exercise, 'primary'));
        $this->assertSame($after[1], $this->slugs($exercise, 'secondary'));
    }

    public static function muscleSets(): array
    {
        return [
            'add secondary' => [[['chest'], []], [['chest'], ['triceps']]],
            'remove secondary' => [[['chest'], ['triceps']], [['chest'], []]],
            'swap roles' => [[['chest'], ['triceps']], [['triceps'], ['chest']]],
            'replace all' => [[['chest', 'triceps'], ['biceps']], [['shoulders'], ['forearms']]],
        ];
    }

    public function test_show_returns_type_and_both_muscle_lists(): void
    {
        $e = $this->exercise(['name' => 'X', 'type' => 'cardio'], ['chest', 'triceps'], ['shoulders']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/exercises/{$e->id}")->assertOk()
            ->assertJsonPath('data.type', 'cardio')
            ->assertJsonCount(2, 'data.primaryMuscles')
            ->assertJsonCount(1, 'data.secondaryMuscles')
            ->assertJsonPath('data.secondaryMuscles.0.slug', 'shoulders');
    }

    public function test_soft_delete_keeps_muscle_relations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $e = $this->exercise(['name' => 'X'], ['chest', 'triceps'], ['shoulders']);

        $this->deleteJson("/api/v1/exercises/{$e->id}")->assertOk();

        $this->assertDatabaseHas('exercises', ['id' => $e->id, 'is_active' => false]);
        $this->assertSame(3, DB::table('exercise_muscle')->where('exercise_id', $e->id)->count());
        $this->assertNotContains('X', $this->names());
    }

    public function test_filters_and_search(): void
    {
        $this->seedCatalog();

        $this->assertCount(4, $this->names());
        $this->assertSame(['Running'], $this->names('?type=cardio'));
        $this->assertSame(['Incline Bench'], $this->names('?difficulty=advanced'));
        $this->assertSame(['Incline Bench'], $this->names('?equipment=dumbbell'));
        $this->assertSame(['Bench Press', 'Incline Bench'], $this->names('?muscles=chest'));
        $this->assertSame(['Bench Press', 'Incline Bench', 'Squat'], $this->names('?muscles=chest,glutes'));
        $this->assertSame(['Bench Press', 'Squat'], $this->names('?type=strength&difficulty=beginner&equipment=barbell'));
        $this->assertSame(['Squat'], $this->names('?type=strength&equipment=barbell&muscles=glutes'));
        $this->assertSame(['Bench Press', 'Incline Bench'], $this->names('?search=bench'));
        $this->assertSame(['Incline Bench'], $this->names('?search=bench&difficulty=advanced&muscles=chest'));
    }

    public function test_muscle_filter_matches_primary_muscles_only(): void
    {
        $this->seedCatalog();

        // Bench Press has triceps only as a secondary muscle.
        $this->assertSame([], $this->names('?muscles=triceps'));
    }

    public function test_failed_muscle_sync_rolls_back_create_and_update(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $existing = $this->exercise(['name' => 'Original'], ['chest']);
        $payload = $this->payload(['chest']);

        DB::listen(function ($query) {
            if (str_contains($query->sql, 'exercise_muscle')) {
                throw new \RuntimeException('boom');
            }
        });

        $this->postJson('/api/v1/exercises', $payload)->assertStatus(500);
        $this->putJson("/api/v1/exercises/{$existing->id}", $payload)->assertStatus(500);

        $this->assertDatabaseMissing('exercises', ['name' => 'Bench Press']);
        $this->assertSame('Original', $existing->fresh()->name);
    }
}
