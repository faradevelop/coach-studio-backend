<?php
namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExerciseSearchTest extends TestCase
{
    use RefreshDatabase;

    private function names(string $query): array
    {
        return array_column($this->getJson("/api/v1/exercises?{$query}")->assertOk()->json('data'), 'name');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Exercise::factory()->create(['name' => 'Bench Press']);
        Exercise::factory()->create(['name' => 'Squat']);
        Exercise::factory()->create(['name' => 'پرس سینه']);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_search_filters_by_name(): void
    {
        $this->assertSame(['Bench Press'], $this->names('search=bench'));
        $this->assertSame(['پرس سینه'], $this->names('search=' . urlencode('سینه')));
        $this->assertSame([], $this->names('search=nothing'));
    }

    public function test_search_matches_all_words_in_any_order(): void
    {
        $this->assertSame(['Bench Press'], $this->names('search=' . urlencode('press bench')));
        $this->assertSame([], $this->names('search=' . urlencode('press squat')));
    }

    public function test_arabic_letter_variants_are_normalized(): void
    {
        // Arabic yeh (ي) typed instead of the Persian one (ی) must still match "سینه".
        $this->assertSame(['پرس سینه'], $this->names('search=' . urlencode('سينه')));
    }

    public function test_like_wildcards_are_literal(): void
    {
        $this->assertSame([], $this->names('search=%25'));
        $this->assertSame([], $this->names('search=_'));
    }

    public function test_blank_search_returns_everything(): void
    {
        $this->assertCount(3, $this->names('search=%20%20'));
        $this->assertCount(3, $this->names('search='));
    }

    public function test_search_combines_with_other_filters_and_ignores_inactive(): void
    {
        Exercise::factory()->create(['name' => 'Bench Dip', 'is_active' => false]);
        Exercise::factory()->create(['name' => 'Bench Row', 'equipment' => 'dumbbell']);

        $this->assertCount(2, $this->names('search=bench'));
        $this->assertSame(['Bench Row'], $this->names('search=bench&equipment=dumbbell'));
    }
}
