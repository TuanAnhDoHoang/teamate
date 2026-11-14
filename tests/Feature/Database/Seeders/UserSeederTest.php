<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

it('creates three verified demo users without resetting existing passwords', function () {
    $demoEmails = [
        'demo1@example.test',
        'demo2@example.test',
        'demo3@example.test',
    ];

    User::query()->whereIn('email', $demoEmails)->delete();

    (new UserSeeder)->run();

    $demoUsers = User::query()->whereIn('email', $demoEmails)->get();

    expect($demoUsers)->toHaveCount(3);
    expect($demoUsers->every(fn (User $user): bool => $user->hasVerifiedEmail()))->toBeTrue();
    expect($demoUsers->every(fn (User $user): bool => Hash::check('TeamateDemoOnly2026!', $user->password)))->toBeTrue();

    $demoUsers->firstOrFail()->forceFill([
        'password' => Hash::make('changed-demo-password'),
    ])->save();

    (new UserSeeder)->run();

    expect(User::query()->whereIn('email', $demoEmails)->count())->toBe(3);
    expect(Hash::check(
        'changed-demo-password',
        User::query()->where('email', 'demo1@example.test')->firstOrFail()->password,
    ))->toBeTrue();
});

it('does not create demo users outside local and testing environments', function () {
    $demoEmails = [
        'demo1@example.test',
        'demo2@example.test',
        'demo3@example.test',
    ];

    User::query()->whereIn('email', $demoEmails)->delete();
    $this->app->detectEnvironment(fn (): string => 'production');

    try {
        (new UserSeeder)->run();
    } finally {
        $this->app->detectEnvironment(fn (): string => 'testing');
    }

    expect(User::query()->whereIn('email', $demoEmails)->exists())->toBeFalse();
});
