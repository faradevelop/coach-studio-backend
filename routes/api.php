<?php

use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\ProgramExerciseController;
use App\Http\Controllers\Api\V1\WorkoutProgramController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Exercises — matches ExerciseRepository contract
    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::get('exercises/{id}', [ExerciseController::class, 'show']);
    Route::post('exercises', [ExerciseController::class, 'store']);
    Route::put('exercises/{id}', [ExerciseController::class, 'update']);
    Route::delete('exercises/{id}', [ExerciseController::class, 'destroy']);

    // Workout Programs — matches WorkoutProgramRepository contract
    Route::get('workout-programs', [WorkoutProgramController::class, 'index']);
    Route::post('workout-programs', [WorkoutProgramController::class, 'store']);
    Route::put('workout-programs/{id}', [WorkoutProgramController::class, 'update']);
    Route::delete('workout-programs/{id}', [WorkoutProgramController::class, 'destroy']);

    // Program Exercises — matches ProgramExerciseRepository contract
    Route::get(
        'workout-programs/{workoutProgramId}/program-exercises',
        [ProgramExerciseController::class, 'index']
    );
    Route::post('program-exercises', [ProgramExerciseController::class, 'store']);
    Route::put('program-exercises/{id}', [ProgramExerciseController::class, 'update']);
    Route::delete('program-exercises/{id}', [ProgramExerciseController::class, 'destroy']);

    // Future-ready reorder endpoint — not yet called by Flutter (see notes below)
    Route::patch('program-exercises/{id}/reorder', [ProgramExerciseController::class, 'reorder']);
});