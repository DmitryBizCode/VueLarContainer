<?php

namespace App\Services;

use App\Models\Container;
use App\Models\SensorType;
use Illuminate\Support\Facades\DB;

class ContainerSensorSyncService
{
    /**
     * Sync container_sensors for a container. When iot_active: ensure all sensor types exist;
     * mandatory always enabled, optional based on $optionalEnabledTypeIds.
     *
     * @param  array<int>  $optionalEnabledTypeIds  Sensor type IDs to enable (optional sensors only)
     */
    public function syncForContainer(Container $container, bool $iotActive, array $optionalEnabledTypeIds = []): void
    {
        if (! $iotActive) {
            return;
        }

        $sensorTypes = SensorType::query()->orderBy('sort_order')->get();
        $now = now();

        foreach ($sensorTypes as $st) {
            $enabled = $st->is_optional
                ? in_array((int) $st->id, $optionalEnabledTypeIds, true)
                : true;

            $exists = DB::table('container_sensors')
                ->where('container_id', $container->id)
                ->where('sensor_type_id', $st->id)
                ->exists();

            if ($exists) {
                DB::table('container_sensors')
                    ->where('container_id', $container->id)
                    ->where('sensor_type_id', $st->id)
                    ->update(['enabled' => $enabled, 'sort_order' => $st->sort_order, 'updated_at' => $now]);
            } else {
                DB::table('container_sensors')->insert([
                    'container_id' => $container->id,
                    'sensor_type_id' => $st->id,
                    'enabled' => $enabled,
                    'sort_order' => $st->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
