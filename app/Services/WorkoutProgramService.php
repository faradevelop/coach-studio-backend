<?php
namespace App\Services;

use App\Models\ProgramExercise;
use App\Models\ProgramExerciseItem;
use App\Models\WorkoutProgram;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
                $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token) . '%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereRaw("workout_programs.title LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('athleteAssignment.athlete', function (Builder $a) use ($like) {
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
     * Creates a fully independent copy of an existing WorkoutProgram,
     * including all of its ProgramExercises and ProgramExerciseItems.
     *
     * ProgramExerciseItems in the copy keep referencing the SAME Exercise
     * rows as the original — the exercise catalog is never duplicated.
     */
    public function duplicate(string $workoutProgramId, ?string $requestedTitle, string $ownerId): WorkoutProgram
    {
        return DB::transaction(function () use ($workoutProgramId, $requestedTitle, $ownerId) {
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

    // private function resolveTitle(?string $requestedTitle, string $originalTitle): string
    // {
    //     $trimmed = trim((string) $requestedTitle);

    //     return $trimmed !== '' ? $trimmed : "{$originalTitle} (Copy)";
    // }

    private function resolveTitle(?string $requestedTitle, string $originalTitle, string $ownerId): string
    {
        $trimmed = trim((string) $requestedTitle);

        if ($trimmed !== '') {
            return $trimmed;
        }

        $baseTitle = preg_replace('/ \(Copy(?: \d+)?\)$/', '', $originalTitle);

        $existingTitles = WorkoutProgram::query()
            ->where('user_id', $ownerId)
            ->where('title', 'like', "{$baseTitle}%")
            ->pluck('title');

        $copyTitle = "{$baseTitle} (Copy)";

        if (! $existingTitles->contains($copyTitle)) {
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
