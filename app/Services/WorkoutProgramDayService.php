<?php

namespace App\Services;

use App\Models\ProgramExercise;
use App\Models\WorkoutProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages the "days" of a WorkoutProgram.
 *
 * There is no `days` table: a program's days are the implicit sequence
 * 1..workout_programs.days_per_week, and each ProgramExercise points at
 * one of them via program_exercises.day. So every operation here is a
 * combination of (a) updating days_per_week and (b) rewriting
 * program_exercises.day — always atomically, under a row lock on the
 * parent WorkoutProgram (same locking strategy as ProgramExerciseService).
 */
class WorkoutProgramDayService
{
    /** Matches the Flutter UI (AppNumberPicker max: 7). */
    public const MAX_DAYS = 7;

    /**
     * Temporary order_index offset used while renumbering days.
     * Must exceed the number of ProgramExercises in any single day.
     * order_index is UNSIGNED INT (max ~4.29e9), so OFFSET * MAX_DAYS is safe.
     */
    private const ORDER_OFFSET = 1_000_000;

    /**
     * Appends a new, empty day at the end of the program.
     */
    public function addDay(string $workoutProgramId): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId) {
            $program = $this->lockProgram($workoutProgramId);

            if ($program->days_per_week >= self::MAX_DAYS) {
                throw ValidationException::withMessages([
                    'day' => ['A program cannot have more than ' . self::MAX_DAYS . ' days.'],
                ]);
            }

            $program->update(['days_per_week' => $program->days_per_week + 1]);

            return $program->fresh();
        });
    }

    /**
     * Deletes a day, deletes every ProgramExercise on it (items cascade at
     * the DB level), shifts all later days down by one, and decrements
     * days_per_week.
     */
    public function deleteDay(string $workoutProgramId, int $day): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId, $day) {
            $program = $this->lockProgram($workoutProgramId);
            $total = $program->days_per_week;

            $this->assertDayExists($day, $total);

            if ($total <= 1) {
                throw ValidationException::withMessages([
                    'day' => ['A program must have at least one day.'],
                ]);
            }

            ProgramExercise::where('workout_program_id', $program->id)
                ->where('day', $day)
                ->delete();

            $mapping = [];
            for ($d = $day + 1; $d <= $total; $d++) {
                $mapping[$d] = $d - 1;
            }
            $this->remapDays($program->id, $mapping);

            $program->update(['days_per_week' => $total - 1]);

            return $program->fresh();
        });
    }

    /**
     * Moves day $from to position $to. The days in between shift by one to
     * fill the gap; every affected ProgramExercise.day is rewritten, and
     * each exercise keeps its own order_index within its day.
     */
    public function reorderDay(string $workoutProgramId, int $from, int $to): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId, $from, $to) {
            $program = $this->lockProgram($workoutProgramId);
            $total = $program->days_per_week;

            $this->assertDayExists($from, $total);

            if ($to < 1 || $to > $total) {
                throw ValidationException::withMessages([
                    'order' => ["The target position must be between 1 and {$total}."],
                ]);
            }

            if ($from === $to) {
                return $program;
            }

            $mapping = [$from => $to];

            if ($from < $to) {
                // Moving down the list: days (from, to] shift up by one.
                for ($d = $from + 1; $d <= $to; $d++) {
                    $mapping[$d] = $d - 1;
                }
            } else {
                // Moving up the list: days [to, from) shift down by one.
                for ($d = $to; $d < $from; $d++) {
                    $mapping[$d] = $d + 1;
                }
            }

            $this->remapDays($program->id, $mapping);

            return $program->fresh();
        });
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    private function lockProgram(string $workoutProgramId): WorkoutProgram
    {
        return WorkoutProgram::where('id', $workoutProgramId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertDayExists(int $day, int $total): void
    {
        if ($day < 1 || $day > $total) {
            abort(404, 'Day not found.');
        }
    }

    /**
     * Rewrites program_exercises.day according to $mapping (old => new).
     * The mapping must be injective (no two old days map to the same new day).
     *
     * UNIQUE(workout_program_id, day, order_index) means a naive "day = day - 1"
     * can collide mid-statement (e.g. day 2 -> 1 while day 1 rows still exist).
     * To avoid it, order_index is temporarily encoded with the row's ORIGINAL day:
     *
     *   1. order_index = order_index + OFFSET * day      (unique: different days
     *                                                     get disjoint ranges)
     *   2. day         = CASE day ... END                 (unique: the encoded
     *                                                     order_index still differs
     *                                                     per original day, and the
     *                                                     mapping is injective)
     *   3. order_index = order_index % OFFSET             (decode back to the
     *                                                     original order_index)
     *
     * Works on MySQL and SQLite.
     *
     * @param array<int,int> $mapping
     */
    private function remapDays(string $workoutProgramId, array $mapping): void
    {
        $mapping = array_filter(
            $mapping,
            fn (int $new, int $old) => $new !== $old,
            ARRAY_FILTER_USE_BOTH
        );

        if ($mapping === []) {
            return;
        }

        $offset = (int) self::ORDER_OFFSET;

        // Phase 1: encode the original day into order_index.
        ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->update(['order_index' => DB::raw("order_index + ({$offset} * day)")]);

        // Phase 2: rewrite day in ONE statement (all integers, safe to inline).
        $cases = '';
        foreach ($mapping as $old => $new) {
            $cases .= sprintf(' WHEN %d THEN %d', (int) $old, (int) $new);
        }

        ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->update(['day' => DB::raw("CASE day{$cases} ELSE day END")]);

        // Phase 3: decode order_index back to its original value.
        ProgramExercise::where('workout_program_id', $workoutProgramId)
            ->update(['order_index' => DB::raw("order_index % {$offset}")]);
    }
}
