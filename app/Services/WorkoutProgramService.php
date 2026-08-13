<?php

namespace App\Services;

use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\WorkoutProgram;
use Illuminate\Support\Facades\DB;

class WorkoutProgramService
{
    /**
     * Creates a fully independent copy of an existing WorkoutProgram,
     * including all of its ProgramExercises and ProgramExerciseItems.
     *
     * ProgramExerciseItems in the copy keep referencing the SAME Exercise
     * rows as the original — the exercise catalog is never duplicated.
     */
    public function duplicate(string $workoutProgramId, ?string $requestedTitle): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId, $requestedTitle) {
            $original = WorkoutProgram::with('programExercises.items')
                ->findOrFail($workoutProgramId);

            $title = $this->resolveTitle($requestedTitle, $original->title);

            $copy = WorkoutProgram::create([
                'title' => $title,
                'goal' => $original->goal,
                'level' => $original->level,
                'days_per_week' => $original->days_per_week,
                'notes' => $original->notes,
                'is_template' => $original->is_template,
            ]);

            foreach ($original->programExercises as $programExercise) {
                $this->copyProgramExercise($programExercise, $copy->id);
            }

            return $copy->fresh();
        });
    }

    // private function resolveTitle(?string $requestedTitle, string $originalTitle): string
    // {
    //     $trimmed = trim((string) $requestedTitle);

    //     return $trimmed !== '' ? $trimmed : "{$originalTitle} (Copy)";
    // }

    private function resolveTitle(?string $requestedTitle, string $originalTitle): string
    {
        $trimmed = trim((string) $requestedTitle);

        if ($trimmed !== '') {
             return $trimmed;
        }

        $baseTitle = preg_replace('/ \(Copy(?: \d+)?\)$/', '', $originalTitle);

        $existingTitles = WorkoutProgram::query()
            ->where('title', 'like', "{$baseTitle}%")
            ->pluck('title');

        $copyTitle = "{$baseTitle} (Copy)";

        if (!$existingTitles->contains($copyTitle)) {
             return $copyTitle;
        }

        $number = 2;

        while ($existingTitles->contains("{$baseTitle} (Copy {$number})")) {
            $number++;
        }

        return "{$baseTitle} (Copy {$number})";
    }

    private function copyProgramExercise(ProgramExercise $original, string $newWorkoutProgramId): void
    {
        $copy = ProgramExercise::create([
            'workout_program_id' => $newWorkoutProgramId,
            'day' => $original->day,
            'order_index' => $original->order_index,
            'sets' => $original->sets,
            'rest' => $original->rest,
            'training_system' => $original->training_system,
        ]);

        foreach ($original->items as $item) {
            $this->copyProgramExerciseItem($item, $copy->id);
        }
    }

    private function copyProgramExerciseItem(ProgramExerciseItem $original, string $newProgramExerciseId): void
    {
        ProgramExerciseItem::create([
            'program_exercise_id' => $newProgramExerciseId,
            'exercise_id' => $original->exercise_id, // unchanged — same catalog entry
            'order_index' => $original->order_index,
            'reps' => $original->reps,
            'tempo' => $original->tempo,
            'description' => $original->description,
        ]);
    }
}
