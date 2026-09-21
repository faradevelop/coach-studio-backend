<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutProgram\ReorderWorkoutProgramDayRequest;
use App\Http\Resources\WorkoutProgramResource;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramDayService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutProgramDayController extends Controller
{
    public function __construct(private readonly WorkoutProgramDayService $service) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $program = $this->resolveProgram($request, $id);

        $updated = $this->service->addDay($program->id);

        return ApiResponse::success(new WorkoutProgramResource($updated), 'Workout program day added', 201);
    }

    public function destroy(Request $request, string $id, int $day): JsonResponse
    {
        $program = $this->resolveProgram($request, $id);

        $updated = $this->service->deleteDay($program->id, $day);

        return ApiResponse::success(new WorkoutProgramResource($updated), 'Workout program day deleted');
    }

    public function reorder(ReorderWorkoutProgramDayRequest $request, string $id, int $day): JsonResponse
    {
        $program = $this->resolveProgram($request, $id);

        $updated = $this->service->reorderDay($program->id, $day, (int) $request->validated('order'));

        return ApiResponse::success(new WorkoutProgramResource($updated), 'Workout program day reordered');
    }

    /**
     * Query-scoped first (404 if not owned/visible), Policy second —
     * same defense-in-depth as WorkoutProgramController.
     */
    private function resolveProgram(Request $request, string $id): WorkoutProgram
    {
        $query = WorkoutProgram::query();

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $program = $query->findOrFail($id);
        $this->authorize('update', $program);

        return $program;
    }
}
