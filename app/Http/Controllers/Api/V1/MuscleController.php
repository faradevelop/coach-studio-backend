<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MuscleResource;
use App\Models\Muscle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MuscleController extends Controller
{
    // Read-only reference data: any authenticated user (coach or admin).
    // Flat list ordered by group, then by the muscle's position in its group.
    public function index(): JsonResponse
    {
        $muscles = Muscle::query()
            ->select('muscles.*')
            ->join('muscle_groups', 'muscle_groups.id', '=', 'muscles.muscle_group_id')
            ->orderBy('muscle_groups.sort_order')
            ->orderBy('muscles.sort_order')
            ->with('group')
            ->get();

        return ApiResponse::success(MuscleResource::collection($muscles));
    }
}
