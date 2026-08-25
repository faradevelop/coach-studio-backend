<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutProgram\DuplicateWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\StoreWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\UpdateWorkoutProgramRequest;
use App\Http\Resources\WorkoutProgramResource;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutProgramController extends Controller
{
    public function __construct(private readonly WorkoutProgramService $service) {}

    public function index(Request $request): JsonResponse
    {
        $programs = $this->scopedQuery($request)->orderBy('title')->get();
        return ApiResponse::success(WorkoutProgramResource::collection($programs));
    }

    public function store(StoreWorkoutProgramRequest $request): JsonResponse
    {
        $this->authorize('create', WorkoutProgram::class);
        $data = $request->validated();

        $program = WorkoutProgram::create([
            'user_id' => $request->user()->id, // never accepted from the client
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
        // Query-scoped first (404 if not owned/visible), Policy checked second — defense in depth.
        $program = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $program);
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $program = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('delete', $program);
        $program->delete();

        return ApiResponse::success(null, 'Workout program deleted');
    }

    public function duplicate(DuplicateWorkoutProgramRequest $request, string $id): JsonResponse
    {
        $original = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('view', $original);

        $copy = $this->service->duplicate($id, $request->validated('title'), $request->user()->id);

        return ApiResponse::success(new WorkoutProgramResource($copy), 'Workout program duplicated', 201);
    }

    private function scopedQuery(Request $request)
    {
        $query = WorkoutProgram::query();

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }
}
