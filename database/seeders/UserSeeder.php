<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'TeamateDemoOnly2026!';

    /**
     * @var array<int, array{name: string, email: string}>
     */
    private const DEMO_USERS = [
        ['name' => 'Demo User 1', 'email' => 'demo1@example.test'],
        ['name' => 'Demo User 2', 'email' => 'demo2@example.test'],
        ['name' => 'Demo User 3', 'email' => 'demo3@example.test'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach (self::DEMO_USERS as $demoUser) {
            $user = User::query()->firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => self::DEMO_PASSWORD,
                ],
            );

            if ($user->wasRecentlyCreated) {
                $user->forceFill([
                    'email_verified_at' => now()->toDateTimeString(),
                ])->save();
            }
        }
    }
}
