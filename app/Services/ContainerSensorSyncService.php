<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerSensor;
use App\Models\SensorType;

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

        foreach ($sensorTypes as $st) {
            $enabled = $st->is_optional
                ? in_array((int) $st->id, $optionalEnabledTypeIds, true)
                : true;

            $row = ContainerSensor::query()
                ->where('container_id', $container->id)
                ->where('sensor_type_id', $st->id)
                ->first();

            if ($row !== null) {
                $row->update([
                    'enabled' => $enabled,
                    'sort_order' => $st->sort_order,
                ]);
            } else {
                ContainerSensor::query()->create([
                    'container_id' => $container->id,
                    'sensor_type_id' => $st->id,
                    'enabled' => $enabled,
                    'sort_order' => $st->sort_order,
                ]);
            }
        }
    }
}
