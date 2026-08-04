<?php

namespace App\Services;

use App\Models\ProgramExercise;
use App\Models\WorkoutProgram;
use Illuminate\Support\Facades\DB;

class ProgramExerciseService
{
    /**
     * Temporary offset used to move a day's order_index values out of the
     * real 1..N range during renumbering, so intermediate writes never
     * collide with UNIQUE(workout_program_id, day, order_index). Must
     * exceed any realistic number of ProgramExercises in a single day.
     */
    private const REORDER_OFFSET = 1_000_000;

    public function create(array $data): ProgramExercise
    {
        return DB::transaction(function () use ($data) {
            $this->lockWorkoutProgram($data['workout_program_id']);

            $nextOrder = $this->nextOrderForDay($data['workout_program_id'], $data['day']);

            $programExercise = ProgramExercise::create([
                'workout_program_id' => $data['workout_program_id'],
                'day' => $data['day'],
                'order_index' => $nextOrder,
                'sets' => $data['sets'],
                'rest' => $data['rest'],
                'training_system' => $data['training_system'],
            ]);

            $this->replaceItems($programExercise, $data['items']);

            return $programExercise->fresh('items.exercise');
        });
    }

    public function update(ProgramExercise $programExercise, array $data): ProgramExercise
    {
        return DB::transaction(function () use ($programExercise, $data) {
            $workoutProgramId = $programExercise->workout_program_id;
            $this->lockWorkoutProgram($workoutProgramId);

            $oldDay = $programExercise->day;
            $newDay = $data['day'];

            if ($oldDay === $newDay) {
                // Day unchanged: preserve the current order_index untouched.
                $programExercise->update([
                    'sets' => $data['sets'],
                    'rest' => $data['rest'],
                    'training_system' => $data['training_system'],
                ]);
            } else {
                // Day changed: treat as a move between days (renumbers both).
                $this->moveToDay($programExercise, $workoutProgramId, $oldDay, $newDay);

                $programExercise->update([
                    'sets' => $data['sets'],
                    'rest' => $data['rest'],
                    'training_system' => $data['training_system'],
                ]);
            }

            $this->replaceItems($programExercise, $data['items']);

            return $programExercise->fresh('items.exercise');
        });
    }

    public function delete(ProgramExercise $programExercise): void
    {
        DB::transaction(function () use ($programExercise) {
            $workoutProgramId = $programExercise->workout_program_id;
            $day = $programExercise->day;

            $this->lockWorkoutProgram($workoutProgramId);

            // ProgramExerciseItems cascade-delete at the database level.
            $programExercise->delete();

            $this->renumberDay($workoutProgramId, $day);
        });
    }

    public function reorder(ProgramExercise $programExercise, int $targetOrder): ProgramExercise
    {
        return DB::transaction(function () use ($programExercise, $targetOrder) {
            $workoutProgramId = $programExercise->workout_program_id;
            $day = $programExercise->day;

            $this->lockWorkoutProgram($workoutProgramId);

            $rows = ProgramExercise::where('workout_program_id', $workoutProgramId)
                ->where('day', $day)
                ->orderBy('order_index')
                ->lockForUpdate()
                ->get();

            $count = $rows->count();
            $target = max(1, min($targetOrder, $count));

            $orderedIds = $rows->pluck('id')->all();
            $orderedIds = array_values(array_filter(
                $orderedIds,
                fn ($id) => $id !== $programExercise->id
            ));
            array_splice($orderedIds, $target - 1, 0, [$programExercise->id]);

            $this->applyOrder($orderedIds);

            return $programExercise->fresh('items.exercise');
        });
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    private function lockWorkoutProgram(string $workoutProgramId): WorkoutProgram
    {
        return WorkoutProgram::where('id', $workoutProgramId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextOrderForDay(string $workoutProgramId, int $day): int
    {
        $max = ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->where('day', $day)
            ->lockForUpdate()
            ->max('order_index');

        return ($max ?? 0) + 1;
    }

    private function renumberDay(string $workoutProgramId, int $day): void
    {
        $ids = ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->where('day', $day)
            ->orderBy('order_index')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $this->applyOrder($ids);
    }

    /**
     * Assigns contiguous 1..N order_index values to the given ProgramExercise
     * ids (in the given order). Uses a temporary offset phase first so that
     * intermediate writes never collide with the UNIQUE constraint.
     *
     * @param array<int, string> $orderedIds
     */
    private function applyOrder(array $orderedIds): void
    {
        // Phase 1: move every row out of the real 1..N range.
        foreach ($orderedIds as $index => $id) {
            ProgramExercise::where('id', $id)->update([
                'order_index' => self::REORDER_OFFSET + $index + 1,
            ]);
        }

        // Phase 2: assign the final contiguous values.
        foreach ($orderedIds as $index => $id) {
            ProgramExercise::where('id', $id)->update([
                'order_index' => $index + 1,
            ]);
        }
    }

    private function moveToDay(
        ProgramExercise $programExercise,
        string $workoutProgramId,
        int $sourceDay,
        int $destinationDay
    ): void {
        // Renumber the source day without the row being moved.
        $sourceIds = ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->where('day', $sourceDay)
            ->where('id', '!=', $programExercise->id)
            ->orderBy('order_index')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $this->applyOrder($sourceIds);

        // Append the moved row to the destination day (temporarily offset).
        $destinationNextOrder = $this->nextOrderForDay($workoutProgramId, $destinationDay);

        $programExercise->update([
            'day' => $destinationDay,
            'order_index' => self::REORDER_OFFSET + $destinationNextOrder,
        ]);

        // Renumber the destination day into a final contiguous sequence.
        $destinationIds = ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->where('day', $destinationDay)
            ->orderBy('order_index')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $this->applyOrder($destinationIds);

        $programExercise->refresh();
    }

    private function replaceItems(ProgramExercise $programExercise, array $items): void
    {
        // Full atomic replace: the Flutter form always submits the complete
        // item list on both create and update, never a partial patch, so
        // delete-and-rebuild exactly matches existing app behavior.
        $programExercise->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $programExercise->items()->create([
                'exercise_id' => $item['exercise_id'],
                'order_index' => $index + 1,
                'reps' => $item['reps'],
                'tempo' => $item['tempo'],
                'description' => $item['description'] ?? null,
            ]);
        }
    }
}