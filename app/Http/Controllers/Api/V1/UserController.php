<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return ApiResponse::success(
            UserResource::collection(
                User::orderBy('username')->get()
            )
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['isActive'] ?? true,
        ]);

        return ApiResponse::success(
            new UserResource($user),
            'User created',
            201
        );
    }

    public function update(
        UpdateUserRequest $request,
        string $id
    ): JsonResponse {
        $target = User::findOrFail($id);

        $this->authorize('update', $target);

        $updated = $this->service->update(
            $request->user(),
            $target,
            $request->validated()
        );

        return ApiResponse::success(
            new UserResource($updated),
            'User updated'
        );
    }

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        $target = User::findOrFail($id);

        $this->authorize('delete', $target);

        $this->service->deactivate(
            $request->user(),
            $target
        );

        return ApiResponse::success(
            null,
            'User deactivated'
        );
    }
}
