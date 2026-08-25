<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->validated('identifier'),
            $request->validated('password'),
        );

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());
        return ApiResponse::success(null, 'Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->service->changePassword(
            $request->user(),
            $request->validated('currentPassword'),
            $request->validated('newPassword'),
        );

        return ApiResponse::success(null, 'Password changed');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->forgotPassword($request->validated('email'));

        return ApiResponse::success(null, 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->service->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        return ApiResponse::success(null, 'Password reset successful');
    }
}
