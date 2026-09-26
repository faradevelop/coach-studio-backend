<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_muscle', function (Blueprint $table) {
            $table->foreignUuid('exercise_id')
                ->constrained('exercises')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('muscle_id')
                ->constrained('muscles')
                // Muscles are reference/catalog data — same reasoning as
                // program_exercise_items.exercise_id: don't let deleting
                // a muscle silently orphan exercise data.
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Composite PK: doubles as the uniqueness constraint and
            // supports lookups by exercise_id via the leftmost prefix.
            $table->primary(['exercise_id', 'muscle_id']);

            // Reverse lookup ("exercises targeting muscle X") needs its
            // own index — the composite PK above doesn't help here.
            $table->index('muscle_id', 'idx_exercise_muscle_muscle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_muscle');
    }
};
