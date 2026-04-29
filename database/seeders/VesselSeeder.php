<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VesselSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('vessels')->where('imo_number', 'like', '910%')->exists()) {
            return;
        }

        $portIds = DB::table('ports')->pluck('id', 'name');
        $hubPorts = array_filter([
            $portIds['Port of Rotterdam'] ?? null,
            $portIds['Port of Hamburg'] ?? null,
            $portIds['Port of Antwerp'] ?? null,
            $portIds['Port of Gdansk'] ?? null,
            $portIds['Port of Valencia'] ?? null,
            $portIds['Port of Piraeus'] ?? null,
            $portIds['Port of Felixstowe'] ?? null,
            $portIds['Port of Odesa'] ?? null,
            $portIds['Port of Genoa'] ?? null,
            $portIds['Port of Bremerhaven'] ?? null,
            $portIds['Port of Le Havre'] ?? null,
            $portIds['Port of Barcelona'] ?? null,
        ]);

        $hubPorts = array_values(array_unique($hubPorts));
        if ($hubPorts === []) {
            return;
        }

        $statuses = ['active', 'active', 'active', 'in_port', 'in_transit', 'scheduled'];
        $now = now();

        for ($i = 1; $i <= 28; $i++) {
            $portId = $hubPorts[($i - 1) % count($hubPorts)];
            $imo = str_pad((string) (9100000 + $i), 7, '0', STR_PAD_LEFT);

            DB::table('vessels')->insert([
                'name' => sprintf('MV Baltic Runner %02d', $i),
                'imo_number' => $imo,
                'capacity_teu' => 1800 + ($i * 80),
                'status' => $statuses[$i % count($statuses)],
                'last_inspection_date' => $now->copy()->subMonths(($i % 8) + 1)->toDateString(),
                'current_port_id' => $portId,
                'berth_busy_until' => null,
                'out_of_service_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
