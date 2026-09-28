<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exercise\StoreExerciseRequest;
use App\Http\Requests\Exercise\UpdateExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exercises = Exercise::query()
            ->where('is_active', true)
            ->when(
                $request->filled('type'),
                fn ($q) => $q->where('type', $request->string('type'))
            )
            ->when(
                $request->filled('difficulty'),
                fn ($q) => $q->where(
                    'difficulty',
                    $request->string('difficulty')
                )
            )
            ->when(
                $request->filled('equipment'),
                fn ($q) => $q->where(
                    'equipment',
                    $request->string('equipment')
                )
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('name', 'like', '%' . trim((string) $request->input('search')) . '%')
            )
            ->when(
                $request->filled('muscles'),
                function ($q) use ($request) {
                    // Accepts a comma-separated list, e.g. ?muscles=chest,triceps
                    $slugs = array_filter(
                        explode(',', $request->string('muscles'))
                    );

                    $q->whereHas(
                        'muscles',
                        fn ($q2) => $q2->whereIn('slug', $slugs)
                    );
                }
            )
            ->with('muscles')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ExerciseResource::collection($exercises)
        );
    }

    public function show(string $id): JsonResponse
    {
        // Intentionally NOT filtered by is_active (Decision 7): a soft-deleted
        // exercise must still resolve when referenced by an existing item.
        $exercise = Exercise::with('muscles')->findOrFail($id);

        return ApiResponse::success(
            new ExerciseResource($exercise)
        );
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $this->authorize('create', Exercise::class);
        $data = $request->validated();

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
            $this->syncMuscles($exercise, $data['muscleSlugs']);
            return $exercise;
        });

        return ApiResponse::success(new ExerciseResource($exercise->fresh('muscles')), 'Exercise created', 201);
    }

    public function update(
        UpdateExerciseRequest $request,
        string $id
    ): JsonResponse {
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
            $this->syncMuscles($exercise, $data['muscleSlugs']);
        });

        return ApiResponse::success(
            new ExerciseResource($exercise->fresh('muscles')),
            'Exercise updated'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $this->authorize('delete', $exercise);
        $exercise->update([
            'is_active' => false,
        ]); // soft delete only

        return ApiResponse::success(
            null,
            'Exercise deleted'
        );
    }

    private function syncMuscles(
        Exercise $exercise,
        array $slugs
    ): void {
        $muscleIds = Muscle::whereIn('slug', $slugs)
            ->pluck('id');

        $exercise->muscles()->sync($muscleIds);
    }
}
