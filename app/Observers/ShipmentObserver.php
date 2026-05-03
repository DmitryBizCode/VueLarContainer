<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Container;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ShipmentObserver
{
    public function updated(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('actual_arrival_date')) {
            return;
        }

        if ($shipment->actual_arrival_date === null) {
            return;
        }

        $arrival = CarbonImmutable::parse((string) $shipment->actual_arrival_date);
        $minDays = max(1, (int) config('logistics.port_operations_min_days', 2));
        $maxDays = max($minDays, (int) config('logistics.port_operations_max_days', 4));
        $opsDays = random_int($minDays, $maxDays);
        $portUntil = $arrival->addDays($opsDays);

        DB::transaction(function () use ($shipment, $portUntil) {
            $shipment->forceFill(['port_operations_until' => $portUntil])->saveQuietly();

            $route = ShippingRoute::query()->find($shipment->route_id);
            if ($route === null) {
                return;
            }

            $vessel = Vessel::query()->find($shipment->vessel_id);
            if ($vessel === null) {
                return;
            }

            $busyUntil = $vessel->berth_busy_until !== null && $vessel->berth_busy_until->gt($portUntil)
                ? $vessel->berth_busy_until
                : $portUntil;

            $vessel->forceFill([
                'berth_busy_until' => $busyUntil,
                'current_port_id' => $route->destination_port_id,
            ])->saveQuietly();

            // Once a shipment arrives, its containers are considered physically in the destination port.
            // We snap container location to destination for realism; map will still show in-transit route until arrival.
            $containerIds = ShipmentItem::query()
                ->where('shipment_id', $shipment->id)
                ->pluck('container_id')
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            if ($containerIds !== []) {
                Container::query()
                    ->whereIn('id', $containerIds)
                    ->update([
                        'current_port_id' => $route->destination_port_id,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}
