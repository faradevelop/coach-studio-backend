<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adding a NOT NULL column with a DEFAULT is safe against existing
        // rows: MySQL, Postgres, and SQLite all backfill the default value
        // into every pre-existing row at add-column time, so no manual
        // UPDATE/backfill step is needed here.
        Schema::table('exercises', function (Blueprint $table) {
            // Backed by App\Enums\ExerciseType. Stored as a plain string
            // (not a native DB enum) for portability across MySQL/SQLite
            // and to avoid a schema migration every time a type is added.
            $table->string('type', 20)->default('strength')->after('name');
            $table->index('type', 'idx_exercises_type');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex('idx_exercises_type');
            $table->dropColumn('type');
        });
    }
};
