<?php

namespace Tests\Feature\Auth;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_access_their_own_workout_programs(): void
    {
        $coach = User::factory()->create();
        WorkoutProgram::factory()->for($coach)->count(2)->create();
        Sanctum::actingAs($coach);

        $response = $this->getJson('/api/v1/workout-programs');
        $this->assertCount(2, $response->json('data'));
    }

    public function test_coach_cannot_access_another_coachs_workout_programs(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        WorkoutProgram::factory()->for($coachB)->create();
        Sanctum::actingAs($coachA);

        $this->assertCount(0, $this->getJson('/api/v1/workout-programs')->json('data'));
    }

    public function test_coach_cannot_update_another_coachs_workout_program(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coachB)->create();
        Sanctum::actingAs($coachA);

        $this->putJson("/api/v1/workout-programs/{$program->id}", $this->validProgramPayload())
            ->assertStatus(404);
    }

    public function test_coach_cannot_delete_another_coachs_workout_program(): void
    {
        $coachA = User::factory()->create();
        $coachB = User::factory()->create();
        $program = WorkoutProgram::factory()->for($coachB)->create();
        Sanctum::actingAs($coachA);

        $this->deleteJson("/api/v1/workout-programs/{$program->id}")->assertStatus(404);
        $this->assertDatabaseHas('workout_programs', ['id' => $program->id]);
    }

    public function test_admin_can_access_all_workout_programs(): void
    {
        $admin = User::factory()->admin()->create();
        WorkoutProgram::factory()->count(3)->create();
        Sanctum::actingAs($admin);

        $this->assertCount(3, $this->getJson('/api/v1/workout-programs')->json('data'));
    }

    public function test_admin_can_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_coach_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/users')->assertStatus(403);
    }

    public function test_coach_can_read_exercises(): void
    {
        Exercise::factory()->count(2)->create();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/exercises')->assertOk();
    }

    public function test_coach_cannot_create_exercises(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/exercises', $this->validExercisePayload())->assertStatus(403);
    }

    public function test_coach_cannot_update_exercises(): void
    {
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/exercises/{$exercise->id}", $this->validExercisePayload())->assertStatus(403);
    }

    public function test_coach_cannot_delete_exercises(): void
    {
        $exercise = Exercise::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/exercises/{$exercise->id}")->assertStatus(403);
    }

    public function test_admin_can_create_update_delete_exercises(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/v1/exercises', $this->validExercisePayload())
            ->assertStatus(201)->json('data.id');

        $this->putJson("/api/v1/exercises/{$id}", $this->validExercisePayload())->assertOk();
        $this->deleteJson("/api/v1/exercises/{$id}")->assertOk();
    }

    private function validProgramPayload(): array
    {
        return ['title' => 'Updated Program', 'goal' => 'strength', 'level' => 'intermediate', 'daysPerWeek' => 3];
    }

    private function validExercisePayload(): array
    {
        return ['name' => 'Squat', 'targetMuscle' => 'Legs', 'difficulty' => 'Beginner', 'equipment' => 'Barbell'];
    }
}
