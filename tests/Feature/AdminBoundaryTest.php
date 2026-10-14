<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Muscle;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_touch_coach_domain_endpoints(): void
    {
        $coach = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coach)->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $calls = [
            ['GET', '/api/v1/workout-programs'],
            ['GET', "/api/v1/workout-programs/{$program->id}"],
            ['PUT', "/api/v1/workout-programs/{$program->id}"],
            ['DELETE', "/api/v1/workout-programs/{$program->id}"],
            ['POST', "/api/v1/workout-programs/{$program->id}/days"],
            ['GET', "/api/v1/workout-programs/{$program->id}/program-exercises"],
            ['POST', '/api/v1/program-exercises'],
            ['GET', '/api/v1/athletes'],
            ['GET', '/api/v1/athletes/summary'],
            ['POST', '/api/v1/athletes'],
            ['PUT', "/api/v1/athletes/{$athlete->id}"],
            ['POST', "/api/v1/athletes/{$athlete->id}/archive"],
            ['POST', "/api/v1/athletes/{$athlete->id}/program-assignments"],
        ];

        foreach ($calls as [$method, $uri]) {
            $this->json($method, $uri, [])->assertStatus(403);
        }

        $this->assertSame(0, $program->fresh()->days_per_week - $program->days_per_week);
    }

    public function test_admin_keeps_exercise_and_coach_management(): void
    {
        $muscle = Muscle::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/exercises', [
            'name' => 'Squat', 'type' => 'strength', 'difficulty' => 'beginner',
            'equipment' => 'barbell', 'muscleSlugs' => [$muscle->slug],
        ])->assertStatus(201);

        User::factory()->count(2)->create();
        $this->assertCount(2, $this->getJson('/api/v1/users?role=coach')->assertOk()->json('data'));
    }

    public function test_coach_cannot_use_admin_endpoints_but_can_read_exercises(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->postJson('/api/v1/exercises', [])->assertStatus(403);
        $this->getJson('/api/v1/exercises')->assertOk();
        $this->getJson('/api/v1/muscles')->assertOk();
    }
}
