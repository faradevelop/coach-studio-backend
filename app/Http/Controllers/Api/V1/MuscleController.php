<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MuscleResource;
use App\Models\Muscle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MuscleController extends Controller
{
    // Read-only reference data — no policy needed, same as Exercise's
    // read side (any authenticated user, coach or admin, may list it).
    public function index(): JsonResponse
    {
        $muscles = Muscle::orderBy('name')->get();

        return ApiResponse::success(MuscleResource::collection($muscles));
    }
}
