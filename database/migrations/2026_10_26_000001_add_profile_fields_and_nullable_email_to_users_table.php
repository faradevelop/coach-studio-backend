<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add the columns with a temporary '' default so existing
        // rows (e.g. the seeded admin) are backfilled instead of failing.
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->default('')->after('username');
            $table->string('last_name', 100)->default('')->after('first_name');
            $table->string('phone', 30)->default('')->after('last_name');
        });

        // Step 2: drop the temporary defaults (change() redefines the column
        // without a default) and make email optional. The existing unique
        // index on email is preserved by change().
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->change();
            $table->string('last_name', 100)->change();
            $table->string('phone', 30)->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restoring NOT NULL requires every row to have an email.
        DB::table('users')->whereNull('email')->orderBy('id')->each(function ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['email' => $user->id . '@placeholder.invalid']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone']);
        });
    }
};
