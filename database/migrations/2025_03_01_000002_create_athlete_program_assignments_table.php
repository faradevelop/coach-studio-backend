<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_program_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('athlete_id')
                ->constrained('athletes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // The athlete-specific CLONE (never the source program).
            $table->foreignUuid('workout_program_id')
                ->constrained('workout_programs')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Program the clone was made from; kept for history only.
            $table->foreignUuid('source_program_id')
                ->nullable()
                ->constrained('workout_programs')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignUuid('assigned_by')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamp('assigned_at');
            // Current assignment  <=>  ended_at IS NULL
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            // A cloned program belongs to exactly one assignment.
            $table->unique('workout_program_id', 'uq_athlete_assignments_program');

            // Current/history lookup per athlete.
            $table->index(['athlete_id', 'ended_at'], 'idx_athlete_assignments_athlete_ended');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_program_assignments');
    }
};
