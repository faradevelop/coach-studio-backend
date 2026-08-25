<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            // 'admin' | 'coach' — validated at the app level, matching the
            // project's existing convention for goal/level/trainingSystem.
            $table->string('role', 20);
            $table->boolean('is_active')->default(true); // soft-delete flag, same pattern as exercises
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
