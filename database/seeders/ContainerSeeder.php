<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContainerSeeder extends Seeder
{
    public function run(): void
    {
        $serials = ['CONT-1001', 'CONT-1002', 'CONT-1003', 'CONT-1004'];
        if (DB::table('containers')->whereIn('serial_number', $serials)->exists()) {
            return;
        }

        $ownerIds = DB::table('owners')->orderBy('id')->pluck('id');
        $portIdsByName = DB::table('ports')->pluck('id', 'name');
        $now = now();

        DB::table('containers')->insert([
            [
                'serial_number' => 'CONT-1001',
                'type' => 'standard',
                'width' => 2.44,
                'length' => 6.06,
                'height' => 2.59,
                'max_weight' => 28200.00,
                'manufacture_date' => '2020-05-14',
                'photo' => null,
                'iot_active' => true,
                'current_status' => 'available',
                'owner_id' => $ownerIds[0] ?? 1,
                'current_port_id' => $portIdsByName['Port of Odesa'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'serial_number' => 'CONT-1002',
                'type' => 'high_cube',
                'width' => 2.44,
                'length' => 12.19,
                'height' => 2.90,
                'max_weight' => 30480.00,
                'manufacture_date' => '2021-07-09',
                'photo' => null,
                'iot_active' => false,
                'current_status' => 'available',
                'owner_id' => $ownerIds[1] ?? 1,
                'current_port_id' => $portIdsByName['Port of Hamburg'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'serial_number' => 'CONT-1003',
                'type' => 'refrigerated',
                'width' => 2.44,
                'length' => 12.19,
                'height' => 2.59,
                'max_weight' => 29000.00,
                'manufacture_date' => '2022-01-18',
                'photo' => null,
                'iot_active' => true,
                'current_status' => 'available',
                'owner_id' => $ownerIds[2] ?? 1,
                'current_port_id' => $portIdsByName['Port of Rotterdam'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'serial_number' => 'CONT-1004',
                'type' => 'flat_rack',
                'width' => 2.44,
                'length' => 12.19,
                'height' => 2.10,
                'max_weight' => 45000.00,
                'manufacture_date' => '2019-11-26',
                'photo' => null,
                'iot_active' => false,
                'current_status' => 'available',
                'owner_id' => $ownerIds[1] ?? 1,
                'current_port_id' => $portIdsByName['Port of Gdansk'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);
    }
}
