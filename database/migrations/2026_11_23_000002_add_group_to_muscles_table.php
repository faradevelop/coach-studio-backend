<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // muscle_group_id is NOT NULL, which is only possible on an empty table.
        // The catalog is re-seeded from database/data/muscle_catalog.json afterwards.
        if (DB::table('muscles')->exists()) {
            throw new RuntimeException(
                'The muscles table is not empty. Empty it (and exercise_muscle) first, '
                . 'or run `php artisan migrate:fresh --seed`.'
            );
        }

        Schema::table('muscles', function (Blueprint $table) {
            $table->foreignUuid('muscle_group_id')
                ->after('id')
                ->constrained('muscle_groups')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('name_en', 100)->nullable()->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name_en');

            $table->index(['muscle_group_id', 'sort_order'], 'idx_muscles_group_sort');
        });
    }

    public function down(): void
    {
        Schema::table('muscles', function (Blueprint $table) {
            $table->dropForeign(['muscle_group_id']);
            $table->dropIndex('idx_muscles_group_sort');
            $table->dropColumn(['muscle_group_id', 'name_en', 'sort_order']);
        });
    }
};
