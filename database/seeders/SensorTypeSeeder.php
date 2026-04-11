<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SensorTypeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSensorTypes();
        $this->syncContainerSensorsForIotContainers();
    }

    protected function seedSensorTypes(): void
    {
        $types = [
            ['slug' => 'door_open', 'name' => 'Датчик відкриття дверей', 'category' => 'safety', 'is_optional' => false, 'telemetry_keys' => ['door_open'], 'sort_order' => 10],
            ['slug' => 'drain_pump', 'name' => 'Дренажний насос', 'category' => 'fluid', 'is_optional' => false, 'telemetry_keys' => ['pump_running'], 'sort_order' => 20],
            ['slug' => 'water_level', 'name' => 'Рівень води', 'category' => 'fluid', 'is_optional' => false, 'telemetry_keys' => ['water_level_pct'], 'sort_order' => 30],
            ['slug' => 'ventilation', 'name' => 'Вентиляція', 'category' => 'climate', 'is_optional' => false, 'telemetry_keys' => ['ventilation_on'], 'sort_order' => 40],
            ['slug' => 'temperature_c', 'name' => 'Температура', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['temperature_c'], 'sort_order' => 50],
            ['slug' => 'humidity_rh', 'name' => 'Вологість', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['humidity_rh'], 'sort_order' => 60],
            ['slug' => 'co2_ppm', 'name' => 'CO₂', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['co2_ppm'], 'sort_order' => 70],
            ['slug' => 'noise_db', 'name' => 'Шум', 'category' => 'environment', 'is_optional' => true, 'telemetry_keys' => ['noise_db'], 'sort_order' => 80],
            ['slug' => 'pressure_hpa', 'name' => 'Тиск', 'category' => 'environment', 'is_optional' => true, 'telemetry_keys' => ['pressure_hpa'], 'sort_order' => 90],
        ];

        $now = now();

        foreach ($types as $row) {
            DB::table('sensor_types')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'telemetry_keys' => json_encode($row['telemetry_keys']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    protected function syncContainerSensorsForIotContainers(): void
    {
        if (! Schema::hasTable('container_sensors') || ! Schema::hasTable('containers')) {
            return;
        }

        $sensorTypes = DB::table('sensor_types')->orderBy('sort_order')->get(['id', 'sort_order']);
        $containerIds = DB::table('containers')->where('iot_active', true)->whereNull('deleted_at')->pluck('id');
        $now = now();

        foreach ($containerIds as $containerId) {
            foreach ($sensorTypes as $st) {
                DB::table('container_sensors')->insertOrIgnore([
                    'container_id' => $containerId,
                    'sensor_type_id' => $st->id,
                    'enabled' => true,
                    'sort_order' => $st->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
