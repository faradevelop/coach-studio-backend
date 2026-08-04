<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workout_program_id')
                ->constrained('workout_programs')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('day');
            $table->unsignedInteger('order_index'); // Dart `order`; renamed — ORDER is a reserved word
            $table->string('sets', 20);
            $table->string('rest', 20);
            $table->string('training_system', 20);
            $table->timestamps();

            // Doubles as the (workout_program_id, day, order_index) lookup index.
            // The separate composite index proposed in Step 2 was redundant and
            // has been removed per your approval correction.
            $table->unique(
                ['workout_program_id', 'day', 'order_index'],
                'uq_program_exercises_workout_day_order'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_exercises');
    }
};