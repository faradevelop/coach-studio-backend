<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MuscleRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exercise\StoreExerciseRequest;
use App\Http\Requests\Exercise\UpdateExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Support\ApiResponse;
use App\Support\TextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    private const WITH_MUSCLES = ['primaryMuscles', 'secondaryMuscles'];

    public function index(Request $request): JsonResponse
    {
        $exercises = Exercise::query()
            ->where('is_active', true)
            ->when(
                $request->filled('search'),
                function ($q) use ($request) {
                    $term = TextNormalizer::normalize(
                        mb_substr((string) $request->input('search'), 0, 100)
                    );

                    // Every word must appear in the name ("press bench" matches "Bench Press").
                    foreach (explode(' ', (string) $term) as $token) {
                        if ($token === '') {
                            continue;
                        }

                        $patterns = TextNormalizer::likePatterns($token);

                        $q->where(function ($w) use ($patterns) {
                            foreach ($patterns as $like) {
                                $w->orWhereRaw("name LIKE ? ESCAPE '!'", [$like]);
                            }
                        });
                    }
                }
            )
            ->when(
                $request->filled('type'),
                fn($q) => $q->where('type', $request->string('type'))
            )
            ->when(
                $request->filled('difficulty'),
                fn($q) => $q->where('difficulty', $request->string('difficulty'))
            )
            ->when(
                $request->filled('equipment'),
                fn($q) => $q->where('equipment', $request->string('equipment'))
            )
            ->when(
                $request->filled('muscles'),
                function ($q) use ($request) {
                    // Comma-separated list, e.g. ?muscles=pectoralis-major,triceps-brachii.
                    // Matches the exercise's PRIMARY (target) muscles only (OR between slugs).
                    $slugs = array_filter(explode(',', (string) $request->input('muscles')));

                    $q->whereHas(
                        'primaryMuscles',
                        fn($q2) => $q2->whereIn('muscles.slug', $slugs)
                    );
                }
            )
            ->with(self::WITH_MUSCLES)
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ExerciseResource::collection($exercises));
    }

    public function show(string $id): JsonResponse
    {
        // Intentionally NOT filtered by is_active (Decision 7): a soft-deleted
        // exercise must still resolve when referenced by an existing item.
        $exercise = Exercise::with(self::WITH_MUSCLES)->findOrFail($id);

        return ApiResponse::success(new ExerciseResource($exercise));
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $this->authorize('create', Exercise::class);
        $data = $request->validated();

        // All-or-nothing: a failed muscle sync must not leave a half-created exercise.
        $exercise = DB::transaction(function () use ($data) {
            $exercise = Exercise::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'difficulty' => $data['difficulty'],
                'equipment' => $data['equipment'],
                'image_url' => $data['imageUrl'] ?? null,
                'video_url' => $data['videoUrl'] ?? null,
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'mistakes' => $data['mistakes'] ?? null,
                'is_active' => $data['isActive'] ?? true,
            ]);

            $this->syncMuscles(
                $exercise,
                $data['primaryMuscleSlugs'],
                $data['secondaryMuscleSlugs'] ?? []
            );

            return $exercise;
        });

        return ApiResponse::success(
            new ExerciseResource($exercise->fresh(self::WITH_MUSCLES)),
            'Exercise created',
            201
        );
    }

    public function update(UpdateExerciseRequest $request, string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $this->authorize('update', $exercise);
        $data = $request->validated();

        DB::transaction(function () use ($exercise, $data) {
            $exercise->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'difficulty' => $data['difficulty'],
                'equipment' => $data['equipment'],
                'image_url' => $data['imageUrl'] ?? null,
                'video_url' => $data['videoUrl'] ?? null,
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'mistakes' => $data['mistakes'] ?? null,
                'is_active' => $data['isActive'] ?? $exercise->is_active,
            ]);

            // PUT = full replacement: an omitted secondary list clears it.
            $this->syncMuscles(
                $exercise,
                $data['primaryMuscleSlugs'],
                $data['secondaryMuscleSlugs'] ?? []
            );
        });

        return ApiResponse::success(
            new ExerciseResource($exercise->fresh(self::WITH_MUSCLES)),
            'Exercise updated'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $this->authorize('delete', $exercise);
        $exercise->update(['is_active' => false]); // soft delete only

        return ApiResponse::success(null, 'Exercise deleted');
    }

    /**
     * Replaces the exercise's muscles with the given primary / secondary slugs
     * (one pivot row per muscle, `role` tells which kind it is).
     *
     * @param array<int,string> $primarySlugs
     * @param array<int,string> $secondarySlugs
     */
    private function syncMuscles(Exercise $exercise, array $primarySlugs, array $secondarySlugs): void
    {
        $ids = Muscle::whereIn('slug', array_merge($primarySlugs, $secondarySlugs))
            ->pluck('id', 'slug');

        $payload = [];
        foreach ($primarySlugs as $slug) {
            $payload[$ids[$slug]] = ['role' => MuscleRole::Primary->value];
        }
        foreach ($secondarySlugs as $slug) {
            $payload[$ids[$slug]] = ['role' => MuscleRole::Secondary->value];
        }

        $exercise->muscles()->sync($payload);
    }
}
