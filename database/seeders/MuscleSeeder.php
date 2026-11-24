<?php

namespace Database\Seeders;

use App\Models\Muscle;
use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MuscleSeeder extends Seeder
{
    public const CATALOG_PATH = 'data/muscle_catalog.json';

    /**
     * Seeds muscle groups + muscles from the single source of truth
     * database/data/muscle_catalog.json. Idempotent: rows are matched by
     * slug, so re-running updates names/order but never duplicates.
     *
     * `name` stores the Persian name (what the apps display), `name_en` the
     * English one. sort_order follows the order in the JSON file.
     * Slugs are stable identifiers: never rename an existing one.
     */
    public function run(): void
    {
        $catalog = json_decode(
            file_get_contents(database_path(self::CATALOG_PATH)),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        DB::transaction(function () use ($catalog) {
            foreach ($catalog['muscleGroups'] as $groupIndex => $groupData) {
                $group = MuscleGroup::updateOrCreate(
                    ['slug' => $groupData['slug']],
                    [
                        'name' => $groupData['nameFa'],
                        'name_en' => $groupData['name'],
                        'sort_order' => $groupIndex + 1,
                    ]
                );

                foreach ($groupData['muscles'] as $muscleIndex => $muscleData) {
                    Muscle::updateOrCreate(
                        ['slug' => $muscleData['slug']],
                        [
                            'muscle_group_id' => $group->id,
                            'name' => $muscleData['nameFa'],
                            'name_en' => $muscleData['name'],
                            'sort_order' => $muscleIndex + 1,
                        ]
                    );
                }
            }
        });
    }
}
