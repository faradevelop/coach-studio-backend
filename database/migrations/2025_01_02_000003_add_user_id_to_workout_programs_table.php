<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: this requires the workout_programs table to be empty
        // (or every existing row manually assigned an owner) before running,
        // since user_id is NOT NULL. Run `php artisan migrate:fresh` in
        // development rather than migrating over existing test data.
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->after('id')
                ->constrained('users')
                ->restrictOnDelete() // users are soft-deleted, never hard-deleted, but this is a safety net
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
