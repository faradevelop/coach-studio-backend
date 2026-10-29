<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // NOTE: env() only works while config is NOT cached. If config:cache is ever
        // used, this seeder skips with the warning below instead of failing.
        $username = env('ADMIN_USERNAME');
        $firstName = env('ADMIN_FIRST_NAME');
        $lastName = env('ADMIN_LAST_NAME');
        $phone = env('ADMIN_PHONE');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$username || !$firstName || !$lastName || !$phone || !$email || !$password) {
            $this->command?->warn(
                'Skipping admin seeder: set ADMIN_USERNAME, ADMIN_FIRST_NAME, ADMIN_LAST_NAME, '
                . 'ADMIN_PHONE, ADMIN_EMAIL, and ADMIN_PASSWORD in .env first.'
            );
            return;
        }

        // Idempotent: running the seeder again never changes an existing account.
        if (User::where('email', $email)->exists()) {
            $this->command?->info('Admin already exists (same email). Nothing to do.');
            return;
        }

        if (User::where('username', $username)->exists()) {
            $this->command?->error('Username is already used by another account. Nothing created.');
            return;
        }

        User::create([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
