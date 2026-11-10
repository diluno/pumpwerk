<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.user.email');
        $password = config('app.user.password');

        if (! $email) {
            throw new RuntimeException('Set APP_USER_EMAIL before seeding.');
        }

        // Never fall back to a guessable password outside local development.
        if (! $password && ! app()->environment('local', 'testing')) {
            throw new RuntimeException('Set APP_USER_PASSWORD before seeding a non-local environment.');
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Sam', 'password' => $password ?: 'password'],
        );
    }
}
