<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supports: WHERE user_id = ? ORDER BY created_at DESC (workout program list).
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_workout_programs_user_created');
        });
    }

    public function down(): void
    {
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->dropIndex('idx_workout_programs_user_created');
        });
    }
};
