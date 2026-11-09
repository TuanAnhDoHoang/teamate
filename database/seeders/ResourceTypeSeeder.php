<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resourceTypes = [
            ['code' => 'team'],
            ['code' => 'project'],
            ['code' => 'folder'],
            ['code' => 'file'],
            ['code' => 'team_member'],
            ['code' => 'project_member'],
            ['code' => 'team_role'],
            ['code' => 'project_role'],
        ];

        foreach ($resourceTypes as $resourceType) {
            DB::table('resource_types')->updateOrInsert(
                ['code' => $resourceType['code']],
            );
        }
    }
}
