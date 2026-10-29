<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutProgramSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $coach = User::factory()->create();

        WorkoutProgram::factory()->for($coach)->create(['title' => 'Push Day', 'goal' => 'strength', 'level' => 'beginner']);
        WorkoutProgram::factory()->for($coach)->create(['title' => 'Leg Day', 'goal' => 'hypertrophy', 'level' => 'advanced']);
        WorkoutProgram::factory()->create(['title' => 'Push Foreign']); // another coach

        $athlete = Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'Ali', 'last_name' => 'Rezaei']);
        $copy = WorkoutProgram::factory()->for($coach)->create(['title' => 'Cut Plan', 'goal' => 'fatLoss', 'is_template' => false]);
        AthleteProgramAssignment::create([
            'athlete_id' => $athlete->id,
            'workout_program_id' => $copy->id,
            'assigned_by' => $coach->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($coach);
    }

    private function titles(string $query = ''): array
    {
        return collect($this->getJson('/api/v1/workout-programs' . $query)->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();
    }

    public function test_no_params_returns_all_own_programs(): void
    {
        $this->assertSame(['Cut Plan', 'Leg Day', 'Push Day'], $this->titles());
    }

    public function test_search_by_title_is_scoped_and_matches_words_in_any_order(): void
    {
        $this->assertSame(['Push Day'], $this->titles('?search=push'));
        $this->assertSame(['Leg Day'], $this->titles('?search=' . urlencode('day leg')));
    }

    public function test_search_by_athlete_name(): void
    {
        $this->assertSame(['Cut Plan'], $this->titles('?search=rezaei'));
        $this->assertSame(['Cut Plan'], $this->titles('?search=' . urlencode('ali cut')));
    }

    public function test_status_goal_and_level_filters(): void
    {
        $this->assertSame(['Cut Plan'], $this->titles('?status=assigned'));
        $this->assertSame(['Leg Day', 'Push Day'], $this->titles('?status=template'));
        $this->assertSame(['Leg Day'], $this->titles('?goal=hypertrophy'));
        $this->assertSame(['Push Day'], $this->titles('?status=template&level=beginner'));
    }

    public function test_like_wildcards_are_literal(): void
    {
        $this->assertSame([], $this->titles('?search=%25'));
    }

    public function test_invalid_params_are_rejected(): void
    {
        $this->getJson('/api/v1/workout-programs?status=bogus')->assertStatus(422);
        $this->getJson('/api/v1/workout-programs?goal=flying')->assertStatus(422);
    }
}
