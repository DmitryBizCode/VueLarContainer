<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            OwnerSeeder::class,
            PortSeeder::class,
            RouteSeeder::class,
            VesselSeeder::class,
            ContainerSeeder::class,
            SensorTypeSeeder::class,
            DemoAccountSeeder::class,
            MaritimeDemoSeeder::class,
        ]);
    }
}
