<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramExercise\ReorderProgramExerciseRequest;
use App\Http\Requests\ProgramExercise\StoreProgramExerciseRequest;
use App\Http\Requests\ProgramExercise\UpdateProgramExerciseRequest;
use App\Http\Resources\ProgramExerciseDetailResource;
use App\Http\Resources\ProgramExerciseResource;
use App\Models\ProgramExercise;
use App\Models\WorkoutProgram;
use App\Services\ProgramExerciseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgramExerciseController extends Controller
{
    public function __construct(private readonly ProgramExerciseService $service)
    {
    }

    public function index(string $workoutProgramId): JsonResponse
    {
        WorkoutProgram::findOrFail($workoutProgramId);

        $programExercises = ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->orderBy('day')
            ->orderBy('order_index')
            ->with('items.exercise')
            ->get();

        return ApiResponse::success(ProgramExerciseDetailResource::collection($programExercises));
    }

    public function store(StoreProgramExerciseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $programExercise = $this->service->create([
            'workout_program_id' => $data['workoutId'],
            'day' => $data['day'],
            'sets' => $data['sets'],
            'rest' => $data['rest'],
            'training_system' => $data['trainingSystem'],
            'items' => array_map(fn ($item) => [
                'exercise_id' => $item['exerciseId'],
                'reps' => $item['reps'],
                'tempo' => $item['tempo'],
                'description' => $item['description'] ?? null,
            ], $data['items']),
        ]);

        return ApiResponse::success(new ProgramExerciseResource($programExercise), 'Program exercise created', 201);
    }

    public function update(UpdateProgramExerciseRequest $request, string $id): JsonResponse
    {
        $programExercise = ProgramExercise::findOrFail($id);
        $data = $request->validated();

        $updated = $this->service->update($programExercise, [
            'day' => $data['day'],
            'sets' => $data['sets'],
            'rest' => $data['rest'],
            'training_system' => $data['trainingSystem'],
            'items' => array_map(fn ($item) => [
                'exercise_id' => $item['exerciseId'],
                'reps' => $item['reps'],
                'tempo' => $item['tempo'],
                'description' => $item['description'] ?? null,
            ], $data['items']),
        ]);

        return ApiResponse::success(new ProgramExerciseResource($updated), 'Program exercise updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $programExercise = ProgramExercise::findOrFail($id);
        $this->service->delete($programExercise);

        return ApiResponse::success(null, 'Program exercise deleted');
    }

    public function reorder(ReorderProgramExerciseRequest $request, string $id): JsonResponse
    {
        $programExercise = ProgramExercise::findOrFail($id);
        $updated = $this->service->reorder($programExercise, (int) $request->validated('order'));

        return ApiResponse::success(new ProgramExerciseResource($updated), 'Program exercise reordered');
    }
}