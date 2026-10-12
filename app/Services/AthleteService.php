<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\AthleteProgramAssignment;
use App\Models\User;
use App\Models\WorkoutProgram;
use App\Support\TextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AthleteService
{
    public function __construct(private readonly WorkoutProgramService $programs) {}

    /**
     * Decides which coach will own a new athlete.
     * - Coach: always themselves; naming another coach is forbidden (403).
     * - Admin: MUST name a valid, active coach; the admin never becomes the owner.
     */
    public function resolveCoach(User $actor, ?string $requestedCoachId): User
    {
        if (!$actor->isAdmin()) {
            if ($requestedCoachId !== null && $requestedCoachId !== $actor->id) {
                throw new AuthorizationException('You cannot create an athlete for another coach.');
            }

            return $actor;
        }

        if ($requestedCoachId === null) {
            throw ValidationException::withMessages([
                'coachId' => ['An admin must select the coach who will own the athlete.'],
            ]);
        }

        $coach = User::where('id', $requestedCoachId)
            ->where('role', 'coach')
            ->where('is_active', true)
            ->first();

        if (!$coach) {
            throw ValidationException::withMessages([
                'coachId' => ['The selected coach is invalid.'],
            ]);
        }

        return $coach;
    }

    public function create(User $coach, array $attributes): Athlete
    {
        return Athlete::create($attributes + ['coach_id' => $coach->id]);
    }

    public function update(Athlete $athlete, array $attributes): Athlete
    {
        return DB::transaction(function () use ($athlete, $attributes) {
            $locked = Athlete::where('id', $athlete->id)->lockForUpdate()->firstOrFail();

            $this->assertNotArchived($locked, 'An archived athlete cannot be edited. Restore it first.');

            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    public function archive(Athlete $athlete): Athlete
    {
        if (!$athlete->isArchived()) {
            $athlete->update(['archived_at' => now()]);
        }

        return $athlete->fresh();
    }

    public function restore(Athlete $athlete): Athlete
    {
        if ($athlete->isArchived()) {
            $athlete->update(['archived_at' => null]);
        }

        return $athlete->fresh();
    }

    /**
     * Assigns (or changes) the athlete's program atomically:
     *  lock athlete -> lock + end current assignment -> clone source
     *  -> create new assignment. Any failure rolls everything back.
     */
    public function assignProgram(
        Athlete $athlete,
        WorkoutProgram $source,
        User $actor,
        ?string $title = null
    ): AthleteProgramAssignment {
        return DB::transaction(function () use ($athlete, $source, $actor, $title) {
            $locked = Athlete::where('id', $athlete->id)->lockForUpdate()->firstOrFail();

            $this->assertNotArchived($locked, 'An archived athlete cannot receive a program. Restore it first.');

            $now = now();

            AthleteProgramAssignment::where('athlete_id', $locked->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (AthleteProgramAssignment $current) => $current->update(['ended_at' => $now]));

            // The clone is owned by the athlete's coach, regardless of who triggered the assign.
            $copy = $this->programs->cloneForAthlete($source, $locked->coach_id, $title);

            $assignment = AthleteProgramAssignment::create([
                'athlete_id' => $locked->id,
                'workout_program_id' => $copy->id,
                'source_program_id' => $source->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
            ]);

            return $assignment->load(['workoutProgram', 'sourceProgram']);
        });
    }

    /**
     * Applies search / status / hasProgram / sort to a (already coach-scoped) query.
     *
     * @param array{search?:?string,status?:?string,hasProgram?:mixed,sort?:?string,direction?:?string} $filters
     */
    public function applyListFilters(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? 'active';

        if ($status === 'active') {
            $query->whereNull('archived_at');
        } elseif ($status === 'archived') {
            $query->whereNotNull('archived_at');
        }

        if (($filters['hasProgram'] ?? null) !== null) {
            filter_var($filters['hasProgram'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereHas('currentAssignment')
                : $query->whereDoesntHave('currentAssignment');
        }

        $term = TextNormalizer::normalize($filters['search'] ?? null);
        if ($term !== null && $term !== '') {
            // Every word must match at least one column, so "Ali Rezaei" matches first+last name.
            foreach (explode(' ', $term) as $token) {
                $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token) . '%';

                $query->where(function (Builder $q) use ($like) {
                    foreach (['first_name', 'last_name', 'phone', 'email'] as $column) {
                        $q->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$like]);
                    }
                });
            }
        }

        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (($filters['sort'] ?? 'name') === 'createdAt') {
            $query->orderBy('created_at', $direction)->orderBy('id');
        } else {
            $query->orderBy('last_name', $direction)->orderBy('first_name', $direction)->orderBy('id');
        }

        return $query;
    }

    private function assertNotArchived(Athlete $athlete, string $message): void
    {
        if ($athlete->isArchived()) {
            throw ValidationException::withMessages(['athlete' => [$message]]);
        }
    }
}
