<?php

namespace App\Services;

use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\WorkoutProgram;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutProgramService
{
    /**
     * Applies search / status / goal / level to an (already owner-scoped) query.
     * Search: every word must match the program title OR the assigned athlete's
     * first/last name, so "ali cut" matches title "Cut Plan" of athlete "Ali".
     *
     * @param array{search?:?string,status?:?string,goal?:?string,level?:?string} $filters
     */
    public function applyListFilters(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? 'all';

        if ($status === 'template') {
            $query->whereDoesntHave('athleteAssignment');
        } elseif ($status === 'assigned') {
            $query->whereHas('athleteAssignment');
        }

        if (! empty($filters['goal'])) {
            $query->where('goal', $filters['goal']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        $term = TextNormalizer::normalize($filters['search'] ?? null);

        if ($term !== null && $term !== '') {
            foreach (explode(' ', $term) as $token) {
                $patterns = TextNormalizer::likePatterns($token);
                $like = $patterns[0]; // Latin-digit form, used for athlete names

                $query->where(function (Builder $q) use ($patterns, $like) {
                    foreach ($patterns as $pattern) {
                        $q->orWhereRaw("workout_programs.title LIKE ? ESCAPE '!'", [$pattern]);
                    }

                    $q->orWhereHas('athleteAssignment.athlete', function (Builder $a) use ($like) {
                        $a->where(function (Builder $n) use ($like) {
                            $n->whereRaw("first_name LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("last_name LIKE ? ESCAPE '!'", [$like]);
                        });
                    });
                });
            }
        }

        return $query;
    }

    /**
     * Updates a program under a row lock. Lowering daysPerWeek below a day that
     * still has exercises is refused (422) — nothing is silently deleted.
     */
    public function update(WorkoutProgram $program, array $attributes): WorkoutProgram
    {
        return DB::transaction(function () use ($program, $attributes) {
            $locked = WorkoutProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();

            $maxUsedDay = (int) ProgramExercise::where('workout_program_id', $locked->id)->max('day');

            if ((int) $attributes['days_per_week'] < $maxUsedDay) {
                throw ValidationException::withMessages([
                    'daysPerWeek' => ["Day {$maxUsedDay} still has exercises. Remove them (or that day) before lowering the number of days."],
                ]);
            }

            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    /**
     * Creates a fully independent copy of an existing WorkoutProgram,
     * including all of its ProgramExercises and ProgramExerciseItems.
     *
     * ProgramExerciseItems in the copy keep referencing the SAME Exercise
     * rows as the original — the exercise catalog is never duplicated.
     */
    public function duplicate(string $workoutProgramId, ?string $requestedTitle, string $ownerId): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId, $requestedTitle, $ownerId) {
            // Same row lock the write services take: waits for in-flight edits.
            WorkoutProgram::whereKey($workoutProgramId)->lockForUpdate()->firstOrFail();
            $original = WorkoutProgram::with('programExercises.items')->findOrFail($workoutProgramId);
            $title    = $this->resolveTitle($requestedTitle, $original->title, $ownerId);

            $copy = WorkoutProgram::create([
                'user_id'       => $ownerId, // NEW — the copy always belongs to whoever requested it
                'title'         => $title,
                'goal'          => $original->goal,
                'level'         => $original->level,
                'days_per_week' => $original->days_per_week,
                'notes'         => $original->notes,
                'is_template'   => true,
            ]);

            foreach ($original->programExercises as $programExercise) {
                $this->copyProgramExercise($programExercise, $copy->id);
            }

            return $copy->fresh();
        });
    }

    /**
     * Clones $source into an athlete-specific program owned by $ownerId
     * (the athlete's coach). The source is only read, never modified.
     * The clone keeps the source title unless $title is given, and is
     * not a template. Safe to call inside an outer transaction.
     */
    public function cloneForAthlete(WorkoutProgram $source, string $ownerId, ?string $title = null): WorkoutProgram
    {
        return DB::transaction(function () use ($source, $ownerId, $title) {
            WorkoutProgram::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $original = WorkoutProgram::with('programExercises.items')->findOrFail($source->id);
            $trimmed  = trim((string) $title);

            $copy = WorkoutProgram::create([
                'user_id'       => $ownerId,
                'title'         => $trimmed !== '' ? $trimmed : $original->title,
                'goal'          => $original->goal,
                'level'         => $original->level,
                'days_per_week' => $original->days_per_week,
                'notes'         => $original->notes,
                'is_template'   => false,
            ]);

            foreach ($original->programExercises as $programExercise) {
                $this->copyProgramExercise($programExercise, $copy->id);
            }

            return $copy->fresh();
        });
    }


    private function resolveTitle(?string $requestedTitle, string $originalTitle, string $ownerId): string
    {
        $trimmed = trim((string) $requestedTitle);

        if ($trimmed !== '') {
            return $trimmed; // length is validated by the request (max:255)
        }

        $baseTitle = preg_replace('/ \(Copy(?: \d+)?\)$/u', '', $originalTitle);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $baseTitle);

        $existingTitles = WorkoutProgram::query()
            ->where('user_id', $ownerId)
            ->where('title', 'like', "{$escaped}%")
            ->pluck('title');

        $number = 1;

        do {
            $suffix = $number === 1 ? ' (Copy)' : " (Copy {$number})";
            // Truncate the base so the whole title always fits the 255-char column.
            $candidate = mb_substr($baseTitle, 0, 255 - mb_strlen($suffix)) . $suffix;
            $number++;
        } while ($existingTitles->contains($candidate));

        return $candidate;
    }

    private function copyProgramExercise(ProgramExercise $original, string $newWorkoutProgramId): void
    {
        $copy = ProgramExercise::create([
            'workout_program_id' => $newWorkoutProgramId,
            'day'                => $original->day,
            'order_index'        => $original->order_index,
            'sets'               => $original->sets,
            'rest'               => $original->rest,
            'training_system'    => $original->training_system,
        ]);

        foreach ($original->items as $item) {
            $this->copyProgramExerciseItem($item, $copy->id);
        }
    }

    private function copyProgramExerciseItem(ProgramExerciseItem $original, string $newProgramExerciseId): void
    {
        ProgramExerciseItem::create([
            'program_exercise_id' => $newProgramExerciseId,
            'exercise_id'         => $original->exercise_id, // unchanged — same catalog entry
            'order_index'         => $original->order_index,
            'reps'                => $original->reps,
            'tempo'               => $original->tempo,
            'description'         => $original->description,
        ]);
    }
}
