<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\ContainerSensor;
use App\Models\SensorType;
use Illuminate\Database\Seeder;

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
            ['slug' => 'door_open', 'name' => 'Door open sensor', 'category' => 'safety', 'is_optional' => false, 'telemetry_keys' => ['door_open'], 'sort_order' => 10],
            ['slug' => 'drain_pump', 'name' => 'Drain pump', 'category' => 'fluid', 'is_optional' => false, 'telemetry_keys' => ['pump_running'], 'sort_order' => 20],
            ['slug' => 'water_level', 'name' => 'Water level', 'category' => 'fluid', 'is_optional' => false, 'telemetry_keys' => ['water_level_pct'], 'sort_order' => 30],
            ['slug' => 'ventilation', 'name' => 'Ventilation', 'category' => 'climate', 'is_optional' => false, 'telemetry_keys' => ['ventilation_on'], 'sort_order' => 40],
            ['slug' => 'temperature_c', 'name' => 'Temperature', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['temperature_c'], 'sort_order' => 50],
            ['slug' => 'humidity_rh', 'name' => 'Humidity', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['humidity_rh'], 'sort_order' => 60],
            ['slug' => 'co2_ppm', 'name' => 'CO₂', 'category' => 'climate', 'is_optional' => true, 'telemetry_keys' => ['co2_ppm'], 'sort_order' => 70],
            ['slug' => 'noise_db', 'name' => 'Noise', 'category' => 'environment', 'is_optional' => true, 'telemetry_keys' => ['noise_db'], 'sort_order' => 80],
            ['slug' => 'pressure_hpa', 'name' => 'Pressure', 'category' => 'environment', 'is_optional' => true, 'telemetry_keys' => ['pressure_hpa'], 'sort_order' => 90],
        ];

        foreach ($types as $row) {
            SensorType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'is_optional' => $row['is_optional'],
                    'telemetry_keys' => $row['telemetry_keys'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }

    protected function syncContainerSensorsForIotContainers(): void
    {
        $sensorTypes = SensorType::query()->orderBy('sort_order')->get(['id', 'sort_order']);
        $containerIds = Container::query()->where('iot_active', true)->whereNull('deleted_at')->pluck('id');
        $now = now();

        foreach ($containerIds as $containerId) {
            foreach ($sensorTypes as $st) {
                ContainerSensor::query()->insertOrIgnore([
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
