<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_exercise_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_exercise_id')
                ->constrained('program_exercises')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('exercise_id')
                ->constrained('exercises')
                ->restrictOnDelete() // Exercise deletion is soft-delete only in the app
                ->cascadeOnUpdate();
            $table->unsignedInteger('order_index'); // Dart `order` (1 or 2 within a block)
            $table->string('reps', 20);
            $table->string('tempo', 20);
            $table->text('description')->nullable();
            $table->timestamps();

            // Doubles as the lookup index for (program_exercise_id, order_index).
            $table->unique(
                ['program_exercise_id', 'order_index'],
                'uq_program_exercise_items_block_order'
            );

            // Distinct column set from the unique constraint above — kept.
            $table->index('exercise_id', 'idx_program_exercise_items_exercise');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_exercise_items');
    }
};