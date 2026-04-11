<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('owners')->whereIn('name', [
            'Blue Horizon Logistics',
            'North Sea Container Group',
            'Atlantic Freight Systems',
        ])->exists();

        if ($existing) {
            return;
        }

        $now = now();
        DB::table('owners')->insert([
            [
                'name' => 'Blue Horizon Logistics',
                'email' => 'operations@bluehorizon.example',
                'phone_number' => '+380441110101',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'North Sea Container Group',
                'email' => 'dispatch@northsea.example',
                'phone_number' => '+494044420202',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Atlantic Freight Systems',
                'email' => 'booking@atlanticfreight.example',
                'phone_number' => '+312010330303',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
