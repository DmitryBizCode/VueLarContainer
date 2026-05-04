<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seed the application's database.
 *
 * Demo accounts (after {@see DemoAccountSeeder}):
 * - Admins: romeobackend@gmail.com, admin2@demo.local, admin3@demo.local — password {@see DemoAccountSeeder::ADMIN_PASSWORD}
 * - Clients: demo@example.com, client2@demo.local, client3@demo.local — password {@see DemoAccountSeeder::CLIENT_PASSWORD}
 *
 * Business narratives: {@see DemoBusinessScenarioSeeder}. High-volume rentals: {@see DemoRentalsBulkSeeder}.
 */
class DatabaseSeeder extends Seeder
{
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
            DemoBusinessScenarioSeeder::class,
            DemoRentalsBulkSeeder::class,
        ]);
    }
}
