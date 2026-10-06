<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('mailbatch.admin');
        $password = $cfg['password'] ?: Str::password(16);

        $admin = User::firstOrNew(['email' => $cfg['email']]);

        if (! $admin->exists) {
            $admin->name = $cfg['name'];
            $admin->password = $password;
            $admin->forceFill(['role' => UserRole::Admin, 'is_active' => true])->save();

            if (! $cfg['password']) {
                $this->command?->warn("Admin created: {$cfg['email']} / {$password}  (change it immediately)");
            } else {
                $this->command?->info("Admin created: {$cfg['email']}");
            }
        }
    }
}
