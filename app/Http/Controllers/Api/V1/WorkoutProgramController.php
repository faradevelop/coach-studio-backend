<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutProgram\DuplicateWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\ListWorkoutProgramsRequest;
use App\Http\Requests\WorkoutProgram\StoreWorkoutProgramRequest;
use App\Http\Requests\WorkoutProgram\UpdateWorkoutProgramRequest;
use App\Http\Resources\WorkoutProgramResource;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkoutProgramController extends Controller
{
    public function __construct(private readonly WorkoutProgramService $service)
    {}
    public function index(ListWorkoutProgramsRequest $request): JsonResponse
    {
        // All programs of the coach: drafts (templates) AND athlete-assigned copies,
        // optionally narrowed by search / status / goal / level.
        $programs = $this->service
            ->applyListFilters($this->scopedQuery($request), $request->validated())
            ->with('athleteAssignment.athlete')
            ->orderBy('title')
            ->get();

        return ApiResponse::success(WorkoutProgramResource::collection($programs));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $program = $this->scopedQuery($request)->with('athleteAssignment.athlete')->findOrFail($id);
        $this->authorize('view', $program);

        return ApiResponse::success(new WorkoutProgramResource($program));
    }

    public function store(StoreWorkoutProgramRequest $request): JsonResponse
    {
        $this->authorize('create', WorkoutProgram::class);
        $data = $request->validated();

        $program = WorkoutProgram::create([
            'user_id'       => $request->user()->id, // never accepted from the client
            'title'         => $data['title'],
            'goal'          => $data['goal'],
            'level'         => $data['level'],
            'days_per_week' => $data['daysPerWeek'],
            'notes'         => $data['notes'] ?? null,
            'is_template'   => true, // a coach-created program is always a draft
        ]);

        return ApiResponse::success(new WorkoutProgramResource($program), 'Workout program created', 201);
    }

    public function update(UpdateWorkoutProgramRequest $request, string $id): JsonResponse
    {
        // Query-scoped first (404 if not owned/visible), Policy checked second — defense in depth.
        $program = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $program);
        $data = $request->validated();

        // is_template is intentionally NOT editable: it is false only for athlete
        // copies, and that is decided exclusively by the assign flow.
        $program->update([
            'title'         => $data['title'],
            'goal'          => $data['goal'],
            'level'         => $data['level'],
            'days_per_week' => $data['daysPerWeek'],
            'notes'         => $data['notes'] ?? null,
        ]);

        return ApiResponse::success(
            new WorkoutProgramResource($program->load('athleteAssignment.athlete')),
            'Workout program updated'
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $program = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('delete', $program);

        // An athlete's program is part of their history; deleting it would also
        // violate the assignment foreign key.
        if ($program->athleteAssignment()->exists()) {
            throw ValidationException::withMessages([
                'program' => ['This program is assigned to an athlete and cannot be deleted.'],
            ]);
        }

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

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }
}
