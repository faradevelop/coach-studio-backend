<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('target_muscle', 100);
            $table->string('difficulty', 50);
            $table->string('equipment', 100);
            $table->string('image_url', 2048)->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('mistakes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Supports: WHERE is_active = 1 ORDER BY name (watchExercises)
            $table->index(['is_active', 'name'], 'idx_exercises_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};