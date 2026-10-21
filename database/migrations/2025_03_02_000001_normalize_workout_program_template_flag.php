<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Invariant: is_template = false  <=>  the program is an athlete copy.
        DB::table('workout_programs')
            ->whereNotIn('id', DB::table('athlete_program_assignments')->select('workout_program_id'))
            ->update(['is_template' => true]);

        DB::table('workout_programs')
            ->whereIn('id', DB::table('athlete_program_assignments')->select('workout_program_id'))
            ->update(['is_template' => false]);
    }

    public function down(): void
    {
        // Data normalization only; nothing to revert.
    }
};
