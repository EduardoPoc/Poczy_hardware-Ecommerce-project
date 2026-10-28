<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'mariana.costa@example.com']);
        if ($user->exists) {
            return;
        }

        $user->name = 'Mariana Costa';
        $user->password = 'PoczyDemo!2026';
        $user->email_verified_at = '2026-01-15 10:00:00';
        $user->save();
    }
}
