<?php

namespace Database\Seeders;

use App\Models\Muscle;
use Illuminate\Database\Seeder;

class MuscleSeeder extends Seeder
{
    /**
     * Seeds the muscle catalog with actual individual muscles — not
     * broad muscle groups/categories (e.g. no "arms", "legs", "core",
     * "full_body"). Slugs are the stable identifier shared with the
     * Flutter client — never rename an existing slug, only add new ones.
     *
     * If the Flutter UI later needs grouping (e.g. "Arms" containing
     * biceps/triceps/forearms), that's a presentation-layer concept and
     * should be modeled in Flutter, not by corrupting this table.
     */
    public function run(): void
    {
        $muscles = [
            ['slug' => 'chest', 'name' => 'سینه'],
            ['slug' => 'upper_back', 'name' => 'پشت فوقانی'],
            ['slug' => 'lats', 'name' => 'لاتیسیموس (بال پشت)'],
            ['slug' => 'lower_back', 'name' => 'پشت تحتانی'],
            ['slug' => 'traps', 'name' => 'ذوزنقه‌ای'],
            ['slug' => 'shoulders', 'name' => 'سرشانه'],
            ['slug' => 'biceps', 'name' => 'جلو بازو'],
            ['slug' => 'triceps', 'name' => 'پشت بازو'],
            ['slug' => 'forearms', 'name' => 'ساعد'],
            ['slug' => 'abs', 'name' => 'شکم'],
            ['slug' => 'obliques', 'name' => 'مورب شکم'],
            ['slug' => 'quadriceps', 'name' => 'چهارسر ران'],
            ['slug' => 'hamstrings', 'name' => 'پشت ران'],
            ['slug' => 'glutes', 'name' => 'باسن'],
            ['slug' => 'calves', 'name' => 'ساق پا'],
            ['slug' => 'hip_flexors', 'name' => 'خم‌کننده‌های لگن'],
            ['slug' => 'adductors', 'name' => 'نزدیک‌کننده‌های ران'],
        ];

        foreach ($muscles as $muscle) {
            Muscle::firstOrCreate(['slug' => $muscle['slug']], $muscle);
        }
    }
}
