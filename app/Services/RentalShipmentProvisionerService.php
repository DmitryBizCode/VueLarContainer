<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentalShipmentProvisionerService
{
    public function __construct(
        private readonly VesselPortScheduleService $vesselSchedule,
    ) {}

    /**
     * Create the first-leg shipment when a rental is approved, if possible.
     */
    public function provisionForApprovedRental(Rental $rental): ?Shipment
    {
        if ($rental->container_id === null || $rental->origin_port_id === null) {
            return null;
        }

        if (ShipmentItem::query()->where('rental_id', $rental->id)->exists()) {
            return null;
        }

        $routeId = $this->resolveFirstLegRouteId($rental);
        if ($routeId === null) {
            return null;
        }

        $route = ShippingRoute::query()->find($routeId);
        if ($route === null || (string) $route->route_status !== 'open') {
            return null;
        }

        $originId = (int) $rental->origin_port_id;
        $start = CarbonImmutable::parse((string) $rental->start_date);
        $vessel = $this->vesselSchedule->pickVesselAtPort($originId, $start);
        if ($vessel === null) {
            return null;
        }

        $departure = $start->max(CarbonImmutable::now());
        $arrival = $departure->addDays(max(1, (int) $route->estimated_days));

        return DB::transaction(function () use ($rental, $route, $vessel, $departure, $arrival) {
            $tracking = $this->uniqueTrackingNumber();

            $shipment = Shipment::query()->create([
                'vessel_id' => $vessel->id,
                'route_id' => $route->id,
                'leg_sequence' => 1,
                'departure_date' => $departure,
                'arrival_date' => $arrival,
                'actual_departure_date' => null,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $tracking,
                'status' => 'scheduled',
            ]);

            ShipmentItem::query()->create([
                'shipment_id' => $shipment->id,
                'container_id' => (int) $rental->container_id,
                'rental_id' => $rental->id,
                'loaded_at' => now(),
                'condition_on_arrival' => 'good',
                'notes' => null,
            ]);

            return $shipment;
        });
    }

    private function resolveFirstLegRouteId(Rental $rental): ?int
    {
        $breakdown = $rental->price_breakdown;
        if (is_array($breakdown) && ! empty($breakdown['route_legs'][0]['route_id'])) {
            return (int) $breakdown['route_legs'][0]['route_id'];
        }

        return $rental->route_id !== null ? (int) $rental->route_id : null;
    }

    private function uniqueTrackingNumber(): string
    {
        do {
            $candidate = 'TRK-'.strtoupper(Str::random(10));
        } while (Shipment::query()->where('tracking_number', $candidate)->exists());

        return $candidate;
    }
}
