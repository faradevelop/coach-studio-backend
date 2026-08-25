<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\ProgramExerciseController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkoutProgramController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public — rate-limited to reduce brute-force / enumeration risk
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Users — admin-only, enforced via UserPolicy
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        // Exercises — read: any user, write: admin only (ExercisePolicy)
        Route::get('exercises', [ExerciseController::class, 'index']);
        Route::get('exercises/{id}', [ExerciseController::class, 'show']);
        Route::post('exercises', [ExerciseController::class, 'store']);
        Route::put('exercises/{id}', [ExerciseController::class, 'update']);
        Route::delete('exercises/{id}', [ExerciseController::class, 'destroy']);

        // Workout Programs — scoped to the authenticated coach, or all for admins
        Route::get('workout-programs', [WorkoutProgramController::class, 'index']);
        Route::post('workout-programs', [WorkoutProgramController::class, 'store']);
        Route::put('workout-programs/{id}', [WorkoutProgramController::class, 'update']);
        Route::delete('workout-programs/{id}', [WorkoutProgramController::class, 'destroy']);
        Route::post('workout-programs/{id}/duplicate', [WorkoutProgramController::class, 'duplicate']);

        // Program Exercises — ownership enforced via the parent WorkoutProgram
        Route::get('workout-programs/{workoutProgramId}/program-exercises', [ProgramExerciseController::class, 'index']);
        Route::post('program-exercises', [ProgramExerciseController::class, 'store']);
        Route::put('program-exercises/{id}', [ProgramExerciseController::class, 'update']);
        Route::delete('program-exercises/{id}', [ProgramExerciseController::class, 'destroy']);
        Route::patch('program-exercises/{id}/reorder', [ProgramExerciseController::class, 'reorder']);
    });
});
