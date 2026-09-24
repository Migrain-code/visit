<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ImageSeeder::class,
            SettingsSeeder::class,
            VehicleSeeder::class,
            TourSeeder::class,
        ]);

        // Örnek gruplar ve kurgusal yolcular canlı siteye YAZILMAZ.
        if (! app()->isProduction()) {
            $this->call(DemoOperationSeeder::class);
        }
    }
}
