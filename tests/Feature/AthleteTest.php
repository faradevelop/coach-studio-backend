<?php
namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AthleteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'firstName'           => 'Ali',
            'lastName'            => 'Rezaei',
            'gender'              => 'male',
            'dateOfBirth'         => '1995-04-12',
            'phone'               => '09121234567',
            'email'               => 'ali@example.com',
            'heightCm'            => 180,
            'weightKg'            => 82.5,
            'fitnessGoal'         => 'strength',
            'experienceLevel'     => 'beginner',
            'injuries'            => null,
            'physicalLimitations' => null,
            'coachNotes'          => 'Prefers mornings',
        ], $overrides);
    }

    // ---------------------------------------------------------------- create

    public function test_coach_can_create_athlete_for_himself(): void
    {
        $coach = User::factory()->create();
        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/athletes', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.coachId', $coach->id)
            ->assertJsonPath('data.firstName', 'Ali')
            ->assertJsonPath('data.isArchived', false)
            ->assertJsonPath('data.currentAssignment', null);

        $this->assertDatabaseHas('athletes', ['coach_id' => $coach->id, 'last_name' => 'Rezaei']);
    }

    public function test_coach_may_pass_his_own_coach_id(): void
    {
        $coach = User::factory()->create();
        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/athletes', $this->payload(['coachId' => $coach->id]))->assertStatus(201);
    }

    public function test_coach_cannot_create_athlete_for_another_coach(): void
    {
        $coach = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($coach);

        $this->postJson('/api/v1/athletes', $this->payload(['coachId' => $other->id]))->assertStatus(403);

        $this->assertSame(0, Athlete::count());
    }





    public function test_athlete_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/athletes', [])->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['gender' => 'robot']))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['dateOfBirth' => now()->addDay()->format('Y-m-d')]))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['heightCm' => 5]))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['weightKg' => 1000]))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['email' => 'nope']))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['fitnessGoal' => 'flying']))->assertStatus(422);
        $this->postJson('/api/v1/athletes', $this->payload(['phone' => 'abc']))->assertStatus(422);
    }

    public function test_phone_and_email_are_not_unique(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/athletes', $this->payload())->assertStatus(201);
        $this->postJson('/api/v1/athletes', $this->payload())->assertStatus(201);
    }

    public function test_persian_digits_are_normalized_on_write(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/athletes', $this->payload(['phone' => '۰۹۱۲۱۲۳۴۵۶۷']))
            ->assertStatus(201)
            ->assertJsonPath('data.phone', '09121234567');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/athletes')->assertStatus(401);
        $this->postJson('/api/v1/athletes', $this->payload())->assertStatus(401);
    }

    // ------------------------------------------------------------- scope/auth

    public function test_coach_cannot_access_another_coachs_athlete(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $athlete  = Athlete::factory()->create(['coach_id' => $owner->id]);
        Sanctum::actingAs($intruder);

        $this->getJson("/api/v1/athletes/{$athlete->id}")->assertStatus(404);
        $this->putJson("/api/v1/athletes/{$athlete->id}", $this->payload())->assertStatus(404);
        $this->postJson("/api/v1/athletes/{$athlete->id}/archive")->assertStatus(404);
        $this->postJson("/api/v1/athletes/{$athlete->id}/restore")->assertStatus(404);
        $this->getJson("/api/v1/athletes/{$athlete->id}/program-history")->assertStatus(404);

        $this->assertNull($athlete->fresh()->archived_at);
    }


    // ----------------------------------------------------------------- update

    public function test_coach_can_update_own_athlete_but_not_the_owner(): void
    {
        $coach   = User::factory()->create();
        $other   = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/athletes/{$athlete->id}", $this->payload(['firstName' => 'Reza', 'coachId' => $other->id]))
            ->assertOk()
            ->assertJsonPath('data.firstName', 'Reza')
            ->assertJsonPath('data.coachId', $coach->id);

        $this->assertSame($coach->id, $athlete->fresh()->coach_id);
    }

    public function test_archived_athlete_cannot_be_edited(): void
    {
        $coach   = User::factory()->create();
        $athlete = Athlete::factory()->archived()->create(['coach_id' => $coach->id]);
        Sanctum::actingAs($coach);

        $this->putJson("/api/v1/athletes/{$athlete->id}", $this->payload())->assertStatus(422);
    }

    // ---------------------------------------------------------- archive/restore

    public function test_coach_can_archive_and_restore_athlete(): void
    {
        $coach   = User::factory()->create();
        $athlete = Athlete::factory()->create(['coach_id' => $coach->id]);
        Sanctum::actingAs($coach);

        $this->postJson("/api/v1/athletes/{$athlete->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.isArchived', true);
        $this->assertNotNull($athlete->fresh()->archived_at);

        // Idempotent
        $this->postJson("/api/v1/athletes/{$athlete->id}/archive")->assertOk();

        $this->postJson("/api/v1/athletes/{$athlete->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.isArchived', false);
        $this->assertNull($athlete->fresh()->archived_at);
    }

    public function test_archived_athlete_remains_accessible_and_keeps_data(): void
    {
        $coach   = User::factory()->create();
        $athlete = Athlete::factory()->archived()->create(['coach_id' => $coach->id, 'first_name' => 'Keep']);
        Sanctum::actingAs($coach);

        $this->getJson("/api/v1/athletes/{$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.isArchived', true)
            ->assertJsonPath('data.firstName', 'Keep');
    }

    // ------------------------------------------------------------------- list

    public function test_list_defaults_to_active_and_supports_status_filter(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->count(2)->create(['coach_id' => $coach->id]);
        Athlete::factory()->archived()->create(['coach_id' => $coach->id]);
        Athlete::factory()->create(); // another coach's athlete
        Sanctum::actingAs($coach);

        $this->assertCount(2, $this->getJson('/api/v1/athletes')->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/athletes?status=active')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/athletes?status=archived')->json('data'));
        $this->assertCount(3, $this->getJson('/api/v1/athletes?status=all')->json('data'));
        $this->getJson('/api/v1/athletes?status=bogus')->assertStatus(422);
    }



    public function test_search_by_name_phone_email_and_persian_digits(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'Ali', 'last_name' => 'Rezaei', 'phone' => '09121234567', 'email' => 'ali@example.com']);
        Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'Sara', 'last_name' => 'Karimi', 'phone' => '09350000000', 'email' => 'sara@test.org']);
        Sanctum::actingAs($coach);

        $this->assertCount(1, $this->getJson('/api/v1/athletes?search=Ali')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/athletes?search=Ali%20Rezaei')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/athletes?search=karimi')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/athletes?search=0935')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/athletes?search=test.org')->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/athletes?search=nobody')->json('data'));

        $persian = http_build_query(['search' => '۰۹۱۲۱']);
        $this->assertCount(1, $this->getJson("/api/v1/athletes?{$persian}")->json('data'));
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'Ali']);
        Sanctum::actingAs($coach);

        $this->assertCount(0, $this->getJson('/api/v1/athletes?search=%25')->json('data'));
    }

    public function test_filter_by_has_program(): void
    {
        $coach   = User::factory()->create();
        $with    = Athlete::factory()->create(['coach_id' => $coach->id]);
        $without = Athlete::factory()->create(['coach_id' => $coach->id]);
        $program = WorkoutProgram::factory()->for($coach)->create();
        AthleteProgramAssignment::create([
            'athlete_id'         => $with->id,
            'workout_program_id' => $program->id,
            'assigned_by'        => $coach->id,
            'assigned_at'        => now(),
        ]);
        Sanctum::actingAs($coach);

        $yes = $this->getJson('/api/v1/athletes?hasProgram=true')->json('data');
        $no  = $this->getJson('/api/v1/athletes?hasProgram=false')->json('data');

        $this->assertSame([$with->id], array_column($yes, 'id'));
        $this->assertSame([$without->id], array_column($no, 'id'));
        $this->assertSame($program->id, $yes[0]['currentAssignment']['programId']);
    }

    public function test_sorting(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'B', 'last_name' => 'Bbb']);
        Athlete::factory()->create(['coach_id' => $coach->id, 'first_name' => 'A', 'last_name' => 'Aaa']);
        Sanctum::actingAs($coach);

        $asc  = array_column($this->getJson('/api/v1/athletes?sort=name&direction=asc')->json('data'), 'lastName');
        $desc = array_column($this->getJson('/api/v1/athletes?sort=name&direction=desc')->json('data'), 'lastName');

        $this->assertSame(['Aaa', 'Bbb'], $asc);
        $this->assertSame(['Bbb', 'Aaa'], $desc);
        $this->getJson('/api/v1/athletes?sort=createdAt&direction=desc')->assertOk();
        $this->getJson('/api/v1/athletes?sort=weird')->assertStatus(422);
    }

    // ---------------------------------------------------------------- summary

    public function test_summary_is_scoped_to_the_coach(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->count(2)->create(['coach_id' => $coach->id]);
        Athlete::factory()->archived()->create(['coach_id' => $coach->id]);
        Athlete::factory()->create(); // other coach
        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/athletes/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.archived', 1)
            ->assertJsonCount(2, 'data.recent');
    }

    public function test_summary_recent_is_limited_to_five(): void
    {
        $coach = User::factory()->create();
        Athlete::factory()->count(7)->create(['coach_id' => $coach->id]);
        Sanctum::actingAs($coach);

        $this->getJson('/api/v1/athletes/summary')->assertJsonCount(5, 'data.recent');
    }
}
