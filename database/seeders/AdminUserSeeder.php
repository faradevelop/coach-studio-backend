<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('ADMIN_USERNAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$username || !$email || !$password) {
            $this->command?->warn(
                'Skipping admin seeder: set ADMIN_USERNAME, ADMIN_EMAIL, and ADMIN_PASSWORD in .env first.'
            );
            return;
        }

        // Idempotent — running the seeder again is a no-op if this email already exists.
        User::firstOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
