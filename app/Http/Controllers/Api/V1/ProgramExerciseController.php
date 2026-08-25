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
use Illuminate\Http\Request;

class ProgramExerciseController extends Controller
{
    public function __construct(private readonly ProgramExerciseService $service) {}

    public function index(Request $request, string $workoutProgramId): JsonResponse
    {
        $program = $this->scopedProgramQuery($request)->findOrFail($workoutProgramId);
        $this->authorize('view', $program);

        $programExercises = ProgramExercise::where('workout_program_id', $program->id)
            ->orderBy('day')->orderBy('order_index')
            ->with('items.exercise')->get();

        return ApiResponse::success(ProgramExerciseDetailResource::collection($programExercises));
    }

    public function store(StoreProgramExerciseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $program = $this->scopedProgramQuery($request)->findOrFail($data['workoutId']);
        $this->authorize('create', [ProgramExercise::class, $program]);

        $programExercise = $this->service->create([
            'workout_program_id' => $program->id,
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
        $programExercise = $this->scopedItemQuery($request)->findOrFail($id);
        $this->authorize('update', $programExercise);
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $programExercise = $this->scopedItemQuery($request)->findOrFail($id);
        $this->authorize('delete', $programExercise);
        $this->service->delete($programExercise);

        return ApiResponse::success(null, 'Program exercise deleted');
    }

    public function reorder(ReorderProgramExerciseRequest $request, string $id): JsonResponse
    {
        $programExercise = $this->scopedItemQuery($request)->findOrFail($id);
        $this->authorize('update', $programExercise);

        $updated = $this->service->reorder($programExercise, (int) $request->validated('order'));

        return ApiResponse::success(new ProgramExerciseResource($updated), 'Program exercise reordered');
    }

    private function scopedProgramQuery(Request $request)
    {
        $query = WorkoutProgram::query();
        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        return $query;
    }

    private function scopedItemQuery(Request $request)
    {
        $query = ProgramExercise::query();
        if (!$request->user()->isAdmin()) {
            $query->whereHas('workoutProgram', fn ($q) => $q->where('user_id', $request->user()->id));
        }
        return $query;
    }
}
