<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Muscle;
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
        return Muscle::firstOrCreate(['slug' => $slug], ['name' => $slug]);
    }

    private function payload(array $slugs, array $overrides = []): array
    {
        foreach ($slugs as $s) { $this->muscle($s); }
        return array_merge([
            'name' => 'Bench Press', 'type' => 'strength', 'difficulty' => 'beginner',
            'equipment' => 'barbell', 'muscleSlugs' => $slugs,
        ], $overrides);
    }

    private function exercise(array $attrs, array $slugs): Exercise
    {
        $e = Exercise::factory()->create($attrs);
        $e->muscles()->sync(collect($slugs)->map(fn ($s) => $this->muscle($s)->id));
        return $e;
    }

    private function slugs(Exercise $e): array
    {
        return $e->fresh()->muscles->pluck('slug')->sort()->values()->all();
    }

    private function names(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/exercises' . $query)->assertOk()->json('data'))
            ->pluck('name')->sort()->values()->all();
    }

    private function seedCatalog(): void
    {
        $this->exercise(['name' => 'Bench Press', 'type' => 'strength', 'difficulty' => 'beginner', 'equipment' => 'barbell'], ['chest', 'triceps']);
        $this->exercise(['name' => 'Incline Bench', 'type' => 'strength', 'difficulty' => 'advanced', 'equipment' => 'dumbbell'], ['chest']);
        $this->exercise(['name' => 'Running', 'type' => 'cardio', 'difficulty' => 'beginner', 'equipment' => 'bodyweight', 'description' => 'not a bench'], ['quadriceps']);
        $this->exercise(['name' => 'Squat', 'type' => 'strength', 'difficulty' => 'beginner', 'equipment' => 'barbell'], ['quadriceps', 'glutes']);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_create_with_multiple_muscles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/v1/exercises', $this->payload(['chest', 'triceps']))
            ->assertStatus(201)->assertJsonCount(2, 'data.muscles')->json('data.id');

        $this->assertSame(['chest', 'triceps'], $this->slugs(Exercise::findOrFail($id)));
    }

    #[DataProvider('muscleSets')]
    public function test_update_synchronizes_muscles(array $before, array $after): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $exercise = $this->exercise(['name' => 'X'], $before);

        $this->putJson("/api/v1/exercises/{$exercise->id}", $this->payload($after, ['name' => 'X']))->assertOk();

        sort($after);
        $this->assertSame($after, $this->slugs($exercise));
    }

    public static function muscleSets(): array
    {
        return [
            'add' => [['chest'], ['chest', 'triceps']],
            'remove' => [['chest', 'triceps'], ['chest']],
            'replace' => [['chest', 'triceps'], ['shoulders', 'biceps']],
        ];
    }

    public function test_show_returns_type_and_muscles(): void
    {
        $e = $this->exercise(['name' => 'X', 'type' => 'cardio'], ['chest', 'triceps']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/exercises/{$e->id}")->assertOk()
            ->assertJsonPath('data.type', 'cardio')->assertJsonCount(2, 'data.muscles');
    }

    public function test_soft_delete_keeps_muscle_relations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $e = $this->exercise(['name' => 'X'], ['chest', 'triceps']);

        $this->deleteJson("/api/v1/exercises/{$e->id}")->assertOk();

        $this->assertDatabaseHas('exercises', ['id' => $e->id, 'is_active' => false]);
        $this->assertSame(2, DB::table('exercise_muscle')->where('exercise_id', $e->id)->count());
        $this->assertNotContains('X', $this->names());
    }

    public function test_filters_and_search(): void
    {
        $this->seedCatalog();

        $this->assertCount(4, $this->names());                                                    // unfiltered
        $this->assertSame(['Running'], $this->names('?type=cardio'));
        $this->assertSame(['Incline Bench'], $this->names('?difficulty=advanced'));
        $this->assertSame(['Incline Bench'], $this->names('?equipment=dumbbell'));
        $this->assertSame(['Bench Press', 'Squat'], $this->names('?muscles=triceps,glutes'));      // OR
        $this->assertSame(['Bench Press', 'Squat'], $this->names('?type=strength&difficulty=beginner&equipment=barbell')); // AND
        $this->assertSame(['Squat'], $this->names('?type=strength&equipment=barbell&muscles=glutes'));
        $this->assertSame(['Bench Press', 'Incline Bench'], $this->names('?search=bench'));         // name only ("Running" description ignored)
        $this->assertSame(['Incline Bench'], $this->names('?search=bench&difficulty=advanced&muscles=chest'));
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
        $this->putJson("/api/v1/exercises/{$existing->id}", $payload + [])->assertStatus(500);

        $this->assertDatabaseMissing('exercises', ['name' => 'Bench Press']);
        $this->assertSame('Original', $existing->fresh()->name);
    }
}
