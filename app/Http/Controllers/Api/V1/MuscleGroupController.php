<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MuscleGroupResource;
use App\Models\MuscleGroup;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MuscleGroupController extends Controller
{
    // Read-only reference data: groups with their muscles nested.
    public function index(): JsonResponse
    {
        $groups = MuscleGroup::query()
            ->orderBy('sort_order')
            ->with('muscles')
            ->get();

        return ApiResponse::success(MuscleGroupResource::collection($groups));
    }
}
