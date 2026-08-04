<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exercise\StoreExerciseRequest;
use App\Http\Requests\Exercise\UpdateExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExerciseController extends Controller
{
    public function index(): JsonResponse
    {
        $exercises = Exercise::where('is_active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ExerciseResource::collection($exercises));
    }

    public function show(string $id): JsonResponse
    {
        // Intentionally NOT filtered by is_active (Decision 7): a soft-deleted
        // exercise must still resolve when referenced by an existing item.
        $exercise = Exercise::findOrFail($id);

        return ApiResponse::success(new ExerciseResource($exercise));
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $exercise = Exercise::create([
            'name' => $data['name'],
            'target_muscle' => $data['targetMuscle'],
            'difficulty' => $data['difficulty'],
            'equipment' => $data['equipment'],
            'image_url' => $data['imageUrl'] ?? null,
            'video_url' => $data['videoUrl'] ?? null,
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'mistakes' => $data['mistakes'] ?? null,
            'is_active' => $data['isActive'] ?? true,
        ]);

        return ApiResponse::success(new ExerciseResource($exercise), 'Exercise created', 201);
    }

    public function update(UpdateExerciseRequest $request, string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $data = $request->validated();

        $exercise->update([
            'name' => $data['name'],
            'target_muscle' => $data['targetMuscle'],
            'difficulty' => $data['difficulty'],
            'equipment' => $data['equipment'],
            'image_url' => $data['imageUrl'] ?? null,
            'video_url' => $data['videoUrl'] ?? null,
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'mistakes' => $data['mistakes'] ?? null,
            'is_active' => $data['isActive'] ?? $exercise->is_active,
        ]);

        return ApiResponse::success(new ExerciseResource($exercise), 'Exercise updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->update(['is_active' => false]); // soft delete only

        return ApiResponse::success(null, 'Exercise deleted');
    }
}