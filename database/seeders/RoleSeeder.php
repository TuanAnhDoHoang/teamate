<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            [
                'code' => 'guest',
                'scope_type' => 'team',
            ],
            [
                'name' => 'Guest',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('roles')->updateOrInsert(
            [
                'code' => 'guest',
                'scope_type' => 'project',
            ],
            [
                'name' => 'Guest',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
