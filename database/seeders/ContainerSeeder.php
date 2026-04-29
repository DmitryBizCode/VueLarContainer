<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContainerSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('containers')->where('serial_number', 'like', 'VL-SEED-%')->exists()) {
            return;
        }

        $ownerIds = DB::table('owners')->orderBy('id')->pluck('id')->values()->all();
        if ($ownerIds === []) {
            return;
        }

        $portIds = DB::table('ports')->pluck('id', 'name');
        $portCycle = array_values(array_filter([
            $portIds['Port of Rotterdam'] ?? null,
            $portIds['Port of Hamburg'] ?? null,
            $portIds['Port of Antwerp'] ?? null,
            $portIds['Port of Gdansk'] ?? null,
            $portIds['Port of Valencia'] ?? null,
            $portIds['Port of Felixstowe'] ?? null,
            $portIds['Port of Odesa'] ?? null,
            $portIds['Port of Genoa'] ?? null,
            $portIds['Port of Bremerhaven'] ?? null,
            $portIds['Port of Le Havre'] ?? null,
            $portIds['Port of Barcelona'] ?? null,
            $portIds['Port of Piraeus'] ?? null,
        ]));

        if ($portCycle === []) {
            return;
        }

        $types = ['standard', 'high_cube', 'refrigerated', 'flat_rack', 'open_top'];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= 56; $i++) {
            $ownerId = $ownerIds[($i - 1) % count($ownerIds)];
            $portId = $portCycle[($i - 1) % count($portCycle)];
            $type = $types[$i % count($types)];
            $isReefer = $type === 'refrigerated';
            $length = $isReefer || $type === 'high_cube' || $type === 'flat_rack' || $type === 'open_top' ? 12.19 : 6.06;
            $height = $type === 'high_cube' ? 2.90 : ($type === 'flat_rack' ? 2.10 : 2.59);
            $maxWeight = $type === 'flat_rack' ? 45000.0 : ($isReefer ? 30480.0 : 28200.0);

            $rows[] = [
                'serial_number' => sprintf('VL-SEED-%05d', $i),
                'type' => $type,
                'width' => 2.44,
                'length' => $length,
                'height' => $height,
                'max_weight' => $maxWeight,
                'manufacture_date' => $now->copy()->subYears(2 + ($i % 5))->toDateString(),
                'photo' => null,
                'iot_active' => $i % 3 !== 0,
                'current_status' => 'available',
                'owner_id' => $ownerId,
                'current_port_id' => $portId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        foreach (array_chunk($rows, 20) as $chunk) {
            DB::table('containers')->insert($chunk);
        }
    }
}
