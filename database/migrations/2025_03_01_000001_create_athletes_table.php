<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coach_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();

            // Neither phone nor email is unique (product decision).
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();

            $table->string('fitness_goal', 50)->nullable();
            $table->string('experience_level', 50)->nullable();

            $table->text('injuries')->nullable();
            $table->text('physical_limitations')->nullable();
            $table->text('coach_notes')->nullable();

            // NULL = active. Archiving never deletes data.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Supports the coach-scoped active/archived list.
            $table->index(['coach_id', 'archived_at'], 'idx_athletes_coach_archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
