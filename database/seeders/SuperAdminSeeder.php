<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) env('SEED_SUPER_ADMIN_EMAIL', 'superadmin@schooldomain.com'));

        $user = User::query()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->fill([
                'name' => 'Super Admin',
                'user_type' => UserType::Platform,
                'status' => 'active',
                'password' => (string) env('SEED_SUPER_ADMIN_PASSWORD', 'ChangeMe!2026'),
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        $user->syncRoles(['super-admin']);

        $this->command?->warn("Platform Super Admin: {$email} — change the password after first login.");
    }
}
