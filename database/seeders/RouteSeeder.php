<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('routes')->exists()) {
            return;
        }

        $portIdsByName = DB::table('ports')->pluck('id', 'name');
        $now = now();

        DB::table('routes')->insert([
            [
                'origin_port_id' => $portIdsByName['Port of Odesa'] ?? 1,
                'destination_port_id' => $portIdsByName['Port of Hamburg'] ?? 1,
                'estimated_days' => 4,
                'distance' => 2000.0,
                'route_status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'origin_port_id' => $portIdsByName['Port of Odesa'] ?? 1,
                'destination_port_id' => $portIdsByName['Port of Rotterdam'] ?? 1,
                'estimated_days' => 5,
                'distance' => 2250.0,
                'route_status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'origin_port_id' => $portIdsByName['Port of Gdansk'] ?? 1,
                'destination_port_id' => $portIdsByName['Port of Valencia'] ?? 1,
                'estimated_days' => 6,
                'distance' => 2900.0,
                'route_status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'origin_port_id' => $portIdsByName['Port of Hamburg'] ?? 1,
                'destination_port_id' => $portIdsByName['Port of Rotterdam'] ?? 1,
                'estimated_days' => 2,
                'distance' => 470.0,
                'route_status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
