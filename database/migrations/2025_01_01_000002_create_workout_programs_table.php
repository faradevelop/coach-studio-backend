<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('goal', 50);
            $table->string('level', 50);
            $table->unsignedTinyInteger('days_per_week');
            $table->text('notes')->nullable();
            $table->boolean('is_template')->default(true);
            $table->timestamps();

            // Supports: ORDER BY title (watchPrograms)
            $table->index('title', 'idx_workout_programs_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_programs');
    }
};