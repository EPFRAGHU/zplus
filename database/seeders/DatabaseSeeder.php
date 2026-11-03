<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Creates the first admin from ADMIN_EMAIL / ADMIN_PASSWORD (a random password is printed if none is set). */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@zplus.local');
        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} already exists.");

            return;
        }
        $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
        $admin = User::query()->create(['name' => 'Admin', 'email' => $email, 'password' => $password, 'role' => 'admin']);
        if (! User::query()->where('is_super_admin', true)->exists()) {
            $admin->forceFill(['is_super_admin' => true])->save();   // the first admin is the owner
        }
        $this->command?->warn("Admin created: {$email}".(env('ADMIN_PASSWORD') ? '' : " / password: {$password} (change it after signing in)"));
    }
}
