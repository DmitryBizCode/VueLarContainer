<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Blue Horizon Logistics',
            'North Sea Container Group',
            'Atlantic Freight Systems',
            'MedLink Carriers',
            'Baltic Star Lines',
            'EuroHub Maritime',
            'Black Sea Trade Fleet',
        ];

        if (DB::table('owners')->whereIn('name', $names)->count() >= count($names)) {
            return;
        }

        $now = now();
        $rows = [
            ['name' => 'Blue Horizon Logistics', 'email' => 'operations@bluehorizon.example', 'phone_number' => '+380441110101'],
            ['name' => 'North Sea Container Group', 'email' => 'dispatch@northsea.example', 'phone_number' => '+494044420202'],
            ['name' => 'Atlantic Freight Systems', 'email' => 'booking@atlanticfreight.example', 'phone_number' => '+312010330303'],
            ['name' => 'MedLink Carriers', 'email' => 'fleet@medlink.example', 'phone_number' => '+30210111222'],
            ['name' => 'Baltic Star Lines', 'email' => 'ops@balticstar.example', 'phone_number' => '+48202233444'],
            ['name' => 'EuroHub Maritime', 'email' => 'chartering@eurohub.example', 'phone_number' => '+31203344555'],
            ['name' => 'Black Sea Trade Fleet', 'email' => 'cargo@blacksea.example', 'phone_number' => '+40204455666'],
        ];

        foreach ($rows as $row) {
            if (DB::table('owners')->where('name', $row['name'])->exists()) {
                continue;
            }
            DB::table('owners')->insert([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
