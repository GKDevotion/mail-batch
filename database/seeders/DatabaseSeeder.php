<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        // Demo data only outside production.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
