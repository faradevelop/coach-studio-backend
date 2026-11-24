<?php

namespace Tests\Feature;

use App\Models\Muscle;
use App\Models\MuscleGroup;
use App\Models\User;
use Database\Seeders\MuscleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MuscleCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        return json_decode(file_get_contents(database_path(MuscleSeeder::CATALOG_PATH)), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_seeder_loads_the_whole_catalog_and_is_idempotent(): void
    {
        $groups = $this->catalog()['muscleGroups'];
        $muscleCount = array_sum(array_map(fn ($g) => count($g['muscles']), $groups));

        $this->seed(MuscleSeeder::class);
        $this->seed(MuscleSeeder::class);

        $this->assertSame(count($groups), MuscleGroup::count());
        $this->assertSame($muscleCount, Muscle::count());
        $this->assertSame(0, Muscle::doesntHave('group')->count());
    }

    public function test_catalog_slugs_are_unique(): void
    {
        $groups = $this->catalog()['muscleGroups'];

        $groupSlugs = array_column($groups, 'slug');
        $muscleSlugs = array_merge(...array_map(fn ($g) => array_column($g['muscles'], 'slug'), $groups));

        $this->assertSame($groupSlugs, array_values(array_unique($groupSlugs)));
        $this->assertSame($muscleSlugs, array_values(array_unique($muscleSlugs)));
    }

    public function test_seeder_stores_persian_name_english_name_and_order(): void
    {
        $this->seed(MuscleSeeder::class);

        $chest = MuscleGroup::where('slug', 'chest')->firstOrFail();
        $this->assertSame('سینه', $chest->name);
        $this->assertSame('Chest', $chest->name_en);
        $this->assertSame(1, $chest->sort_order);

        $minor = Muscle::where('slug', 'pectoralis-minor')->firstOrFail();
        $this->assertSame($chest->id, $minor->muscle_group_id);
        $this->assertSame(2, $minor->sort_order);
    }

    public function test_groups_endpoint_returns_nested_muscles_in_catalog_order(): void
    {
        $this->seed(MuscleSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/muscle-groups')->assertOk();

        $expected = array_column($this->catalog()['muscleGroups'], 'slug');
        $this->assertSame($expected, array_column($response->json('data'), 'slug'));
        $this->assertSame(
            array_column($this->catalog()['muscleGroups'][0]['muscles'], 'slug'),
            array_column($response->json('data.0.muscles'), 'slug')
        );
    }

    public function test_muscles_endpoint_is_flat_ordered_and_includes_group(): void
    {
        $this->seed(MuscleSeeder::class);
        Sanctum::actingAs(User::factory()->admin()->create());

        $data = $this->getJson('/api/v1/muscles')->assertOk()->json('data');

        $this->assertSame('pectoralis-major', $data[0]['slug']);
        $this->assertSame('chest', $data[0]['group']['slug']);
        $this->assertArrayHasKey('nameEn', $data[0]);
    }

    public function test_catalog_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/muscles')->assertStatus(401);
        $this->getJson('/api/v1/muscle-groups')->assertStatus(401);
    }

    public function test_catalog_is_read_only_through_the_api(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/muscles', [])->assertStatus(405);
        $this->postJson('/api/v1/muscle-groups', [])->assertStatus(405);
    }

    public function test_a_group_with_muscles_cannot_be_deleted_at_db_level(): void
    {
        $this->seed(MuscleSeeder::class);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MuscleGroup::where('slug', 'chest')->firstOrFail()->delete();
    }
}
