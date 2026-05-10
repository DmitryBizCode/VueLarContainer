<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo logins for local/staging. Passwords: admins {@see self::ADMIN_PASSWORD}, clients {@see self::CLIENT_PASSWORD}.
 */
class DemoAccountSeeder extends Seeder
{
    public const ADMIN_PASSWORD = '123456789';

    public const CLIENT_PASSWORD = 'password';

    public function run(): void
    {
        $countryId = Country::query()->orderBy('id')->value('id');

        $admins = [
            ['email' => 'admin@gmail.com', 'first_name' => 'Admin', 'last_name' => 'Admin', 'company_name' => 'Admin Demo'],
            ['email' => 'admin2@demo.local', 'first_name' => 'Sofia', 'last_name' => 'Martinez', 'company_name' => 'VueLar Operations'],
            ['email' => 'admin3@demo.local', 'first_name' => 'Jonas', 'last_name' => 'Lindqvist', 'company_name' => 'Nordic Fleet Admin'],
        ];

        foreach ($admins as $row) {
            User::query()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'company_name' => $row['company_name'],
                    'password' => Hash::make(self::ADMIN_PASSWORD),
                    'email_verified_at' => now(),
                    'account_status' => 'active',
                    'role' => 'admin',
                    'country_id' => $countryId,
                ]
            );
        }

        $clients = [
            ['email' => 'demo@example.com', 'first_name' => 'Demo', 'last_name' => 'Client', 'company_name' => 'Demo Logistics'],
            ['email' => 'client2@demo.local', 'first_name' => 'Elena', 'last_name' => 'Vogel', 'company_name' => 'Rhine Cargo GmbH'],
            ['email' => 'client3@demo.local', 'first_name' => 'Marcus', 'last_name' => 'Okonkwo', 'company_name' => 'Atlantic Traders Ltd'],
        ];

        foreach ($clients as $row) {
            User::query()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'company_name' => $row['company_name'],
                    'password' => Hash::make(self::CLIENT_PASSWORD),
                    'email_verified_at' => now(),
                    'account_status' => 'active',
                    'role' => 'client',
                    'country_id' => $countryId,
                ]
            );
        }
    }
}
