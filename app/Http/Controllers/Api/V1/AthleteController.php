<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Athlete\AssignAthleteProgramRequest;
use App\Http\Requests\Athlete\ListAthletesRequest;
use App\Http\Requests\Athlete\StoreAthleteRequest;
use App\Http\Requests\Athlete\UpdateAthleteRequest;
use App\Http\Resources\AthleteProgramAssignmentResource;
use App\Http\Resources\AthleteResource;
use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
use App\Models\WorkoutProgram;
use App\Services\AthleteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AthleteController extends Controller
{
    public function __construct(private readonly AthleteService $service) {}

    public function index(ListAthletesRequest $request): JsonResponse
    {
        $query = $this->service->applyListFilters(
            $this->scopedQuery($request),
            $request->validated()
        );

        $athletes = $query->with('currentAssignment.workoutProgram')->get();

        return ApiResponse::success(AthleteResource::collection($athletes));
    }

    public function summary(Request $request): JsonResponse
    {
        $total = $this->scopedQuery($request)->count();
        $active = $this->scopedQuery($request)->whereNull('archived_at')->count();

        $recent = $this->scopedQuery($request)
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'archived' => $total - $active,
            'recent' => AthleteResource::collection($recent),
        ]);
    }

    public function store(StoreAthleteRequest $request): JsonResponse
    {
        $this->authorize('create', Athlete::class);
        $data = $request->validated();

        $coach = $this->service->resolveCoach($request->user(), $data['coachId'] ?? null);
        $athlete = $this->service->create($coach, $this->profileAttributes($data));

        return ApiResponse::success(
            new AthleteResource($athlete->load('currentAssignment.workoutProgram')),
            'Athlete created',
            201
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('view', $athlete);

        return ApiResponse::success(
            new AthleteResource($athlete->load('currentAssignment.workoutProgram'))
        );
    }

    public function update(UpdateAthleteRequest $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $athlete);

        $updated = $this->service->update($athlete, $this->profileAttributes($request->validated()));

        return ApiResponse::success(
            new AthleteResource($updated->load('currentAssignment.workoutProgram')),
            'Athlete updated'
        );
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $athlete);

        $archived = $this->service->archive($athlete);

        return ApiResponse::success(
            new AthleteResource($archived->load('currentAssignment.workoutProgram')),
            'Athlete archived'
        );
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $athlete);

        $restored = $this->service->restore($athlete);

        return ApiResponse::success(
            new AthleteResource($restored->load('currentAssignment.workoutProgram')),
            'Athlete restored'
        );
    }

    public function programHistory(Request $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('view', $athlete);

        $assignments = AthleteProgramAssignment::where('athlete_id', $athlete->id)
            ->with(['workoutProgram', 'sourceProgram'])
            ->orderByRaw('ended_at IS NULL DESC') // current assignment first
            ->orderByDesc('assigned_at')
            ->get();

        return ApiResponse::success(AthleteProgramAssignmentResource::collection($assignments));
    }

    public function assignProgram(AssignAthleteProgramRequest $request, string $id): JsonResponse
    {
        $athlete = $this->scopedQuery($request)->findOrFail($id);
        $this->authorize('update', $athlete);
        $data = $request->validated();

        // Defense in depth, same as WorkoutProgramController: scoped query, then Policy.
        $source = $this->scopedProgramQuery($request)->findOrFail($data['sourceProgramId']);
        $this->authorize('view', $source);

        $assignment = $this->service->assignProgram(
            $athlete,
            $source,
            $request->user(),
            $data['title'] ?? null
        );

        return ApiResponse::success(
            new AthleteProgramAssignmentResource($assignment),
            'Program assigned',
            201
        );
    }

    /** camelCase request keys -> snake_case columns (coachId is never mapped). */
    private function profileAttributes(array $data): array
    {
        return [
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['dateOfBirth'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'height_cm' => $data['heightCm'] ?? null,
            'weight_kg' => $data['weightKg'] ?? null,
            'fitness_goal' => $data['fitnessGoal'] ?? null,
            'experience_level' => $data['experienceLevel'] ?? null,
            'injuries' => $data['injuries'] ?? null,
            'physical_limitations' => $data['physicalLimitations'] ?? null,
            'coach_notes' => $data['coachNotes'] ?? null,
        ];
    }

    private function scopedQuery(Request $request)
    {
        $query = Athlete::query();

        if (!$request->user()->isAdmin()) {
            $query->where('coach_id', $request->user()->id);
        }

        return $query;
    }

    private function scopedProgramQuery(Request $request)
    {
        $query = WorkoutProgram::query();

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }
}
