<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AthleteController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MuscleController;
use App\Http\Controllers\Api\V1\ProgramExerciseController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkoutProgramController;
use App\Http\Controllers\Api\V1\WorkoutProgramDayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public — rate-limited to reduce brute-force / enumeration risk
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        // ── Any authenticated user ───────────────────────────────────────
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Read-only reference data: the Exercise Bank is read by coaches and managed by admins.
        Route::get('exercises', [ExerciseController::class, 'index']);
        Route::get('exercises/{id}', [ExerciseController::class, 'show']);
        Route::get('muscles', [MuscleController::class, 'index']);

        // ── Admin only: Exercise management + Coach management ───────────
        Route::middleware('role:admin')->group(function () {
            Route::post('exercises', [ExerciseController::class, 'store']);
            Route::put('exercises/{id}', [ExerciseController::class, 'update']);
            Route::delete('exercises/{id}', [ExerciseController::class, 'destroy']);

            // Coach management. `DELETE` deactivates (never hard-deletes).
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{id}', [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);
        });

        // ── Coach only: own programs + own athletes ──────────────────────
        // Ownership is additionally enforced by scoped queries + Policies.
        Route::middleware('role:coach')->group(function () {
            Route::get('workout-programs', [WorkoutProgramController::class, 'index']);
            Route::get('workout-programs/{id}', [WorkoutProgramController::class, 'show']);
            Route::post('workout-programs', [WorkoutProgramController::class, 'store']);
            Route::put('workout-programs/{id}', [WorkoutProgramController::class, 'update']);
            Route::delete('workout-programs/{id}', [WorkoutProgramController::class, 'destroy']);
            Route::post('workout-programs/{id}/duplicate', [WorkoutProgramController::class, 'duplicate']);

            // Days are implicit (1..days_per_week); ownership via the parent WorkoutProgram.
            Route::post('workout-programs/{id}/days', [WorkoutProgramDayController::class, 'store']);
            Route::delete('workout-programs/{id}/days/{day}', [WorkoutProgramDayController::class, 'destroy'])->whereNumber('day');
            Route::patch('workout-programs/{id}/days/{day}/reorder', [WorkoutProgramDayController::class, 'reorder'])->whereNumber('day');

            Route::get('workout-programs/{workoutProgramId}/program-exercises', [ProgramExerciseController::class, 'index']);
            Route::post('program-exercises', [ProgramExerciseController::class, 'store']);
            Route::put('program-exercises/{id}', [ProgramExerciseController::class, 'update']);
            Route::delete('program-exercises/{id}', [ProgramExerciseController::class, 'destroy']);
            Route::patch('program-exercises/{id}/reorder', [ProgramExerciseController::class, 'reorder']);

            // `summary` must stay above `{id}`.
            Route::get('athletes', [AthleteController::class, 'index']);
            Route::get('athletes/summary', [AthleteController::class, 'summary']);
            Route::post('athletes', [AthleteController::class, 'store']);
            Route::get('athletes/{id}', [AthleteController::class, 'show']);
            Route::put('athletes/{id}', [AthleteController::class, 'update']);
            Route::post('athletes/{id}/archive', [AthleteController::class, 'archive']);
            Route::post('athletes/{id}/restore', [AthleteController::class, 'restore']);
            Route::get('athletes/{id}/program-history', [AthleteController::class, 'programHistory']);
            Route::post('athletes/{id}/program-assignments', [AthleteController::class, 'assignProgram']);
        });
    });
});
