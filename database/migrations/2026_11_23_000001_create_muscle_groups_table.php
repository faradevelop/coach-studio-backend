<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Stable identifier shared with the Flutter client (never renamed).
            $table->string('slug', 100)->unique();
            $table->string('name', 100);                // Persian display name
            $table->string('name_en', 100)->nullable(); // English (catalog reference)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order', 'idx_muscle_groups_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muscle_groups');
    }
};
