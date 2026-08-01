<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('APP_USER_EMAIL', 'sam@diluno.com')],
            [
                'name' => 'Sam',
                'password' => Hash::make(env('APP_USER_PASSWORD', 'password')),
            ],
        );
    }
}
