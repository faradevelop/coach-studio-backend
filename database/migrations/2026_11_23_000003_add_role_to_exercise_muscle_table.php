<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_muscle', function (Blueprint $table) {
            // 'primary' (target) | 'secondary' (assisting). Validated at app level
            // (App\Enums\MuscleRole), same convention as the other string enums.
            // The composite PK (exercise_id, muscle_id) is kept on purpose:
            // a muscle cannot be both primary and secondary for one exercise.
            $table->string('role', 20)->default('primary');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_muscle', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
