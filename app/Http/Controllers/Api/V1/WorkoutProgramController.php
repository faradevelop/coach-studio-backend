<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutProgram\StoreWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\UpdateWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\DuplicateWorkoutProgramRequest;
use App\Http\Resources\WorkoutProgramResource;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WorkoutProgramController extends Controller
{
      public function __construct(private readonly WorkoutProgramService $service)
    {
    }

    public function index(): JsonResponse
    {
        $programs = WorkoutProgram::orderBy('title')->get();

        return ApiResponse::success(WorkoutProgramResource::collection($programs));
    }

    public function store(StoreWorkoutProgramRequest $request): JsonResponse
    {
        $data = $request->validated();

        $program = WorkoutProgram::create([
            'title' => $data['title'],
            'goal' => $data['goal'],
            'level' => $data['level'],
            'days_per_week' => $data['daysPerWeek'],
            'notes' => $data['notes'] ?? null,
            'is_template' => $data['isTemplate'] ?? true,
        ]);

        return ApiResponse::success(new WorkoutProgramResource($program), 'Workout program created', 201);
    }

    public function update(UpdateWorkoutProgramRequest $request, string $id): JsonResponse
    {
        $program = WorkoutProgram::findOrFail($id);
        $data = $request->validated();

        $program->update([
            'title' => $data['title'],
            'goal' => $data['goal'],
            'level' => $data['level'],
            'days_per_week' => $data['daysPerWeek'],
            'notes' => $data['notes'] ?? null,
            'is_template' => $data['isTemplate'] ?? $program->is_template,
        ]);

        return ApiResponse::success(new WorkoutProgramResource($program), 'Workout program updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $program = WorkoutProgram::findOrFail($id);
        $program->delete(); // hard delete — cascades to program_exercises

        return ApiResponse::success(null, 'Workout program deleted');
    }

        public function duplicate(DuplicateWorkoutProgramRequest $request, string $id): JsonResponse
    {
        $copy = $this->service->duplicate($id, $request->validated('title'));

        return ApiResponse::success(new WorkoutProgramResource($copy), 'Workout program duplicated', 201);
    }
}
