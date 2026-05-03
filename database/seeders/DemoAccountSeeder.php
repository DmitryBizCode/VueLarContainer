<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local / demo logins. Sample rentals may be added by {@see MaritimeDemoSeeder}.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = Country::query()->orderBy('id')->value('id');

        User::query()->firstOrCreate(
            ['email' => 'romeobackend@gmail.com'],
            [
                'first_name' => 'Romeo',
                'last_name' => 'Back',
                'company_name' => 'VueLar Demo',
                'password' => Hash::make('123456789'),
                'email_verified_at' => now(),
                'account_status' => 'active',
                'role' => 'admin',
                'country_id' => $countryId,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Client',
                'company_name' => 'Demo Logistics',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'account_status' => 'active',
                'role' => 'client',
                'country_id' => $countryId,
            ]
        );
    }
}
