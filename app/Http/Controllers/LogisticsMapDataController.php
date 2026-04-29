<?php

namespace App\Http\Controllers;

use App\Models\ContainerSimulationSnapshot;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\Vessel;
use App\Services\LogisticsMapGeometryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsMapDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $now = CarbonImmutable::now();

        /** @var list<string> $allowedPortNames */
        $allowedPortNames = (array) config('logistics_map.port_names', []);
        $allowedPortNames = array_values(array_filter(array_map('strval', $allowedPortNames)));

        $allowedPortIds = $allowedPortNames === []
            ? []
            : Port::query()
                ->whereIn('name', $allowedPortNames)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

        $ports = Port::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($allowedPortIds !== [], fn ($q) => $q->whereIn('id', $allowedPortIds))
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'latitude', 'longitude'])
            ->map(fn (Port $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'city' => $p->city,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
            ])
            ->values();

        $routeEdges = ShippingRoute::query()
            ->where('route_status', 'open')
            ->when($allowedPortIds !== [], function ($q) use ($allowedPortIds) {
                $q->whereIn('origin_port_id', $allowedPortIds)
                    ->whereIn('destination_port_id', $allowedPortIds);
            })
            ->with(['originPort:id,latitude,longitude', 'destinationPort:id,latitude,longitude'])
            ->orderBy('id')
            ->get()
            ->map(function (ShippingRoute $route) {
                $o = $route->originPort;
                $d = $route->destinationPort;
                if ($o?->latitude === null || $o?->longitude === null
                    || $d?->latitude === null || $d?->longitude === null) {
                    return null;
                }

                $path = LogisticsMapGeometryService::resolvePath(
                    (float) $o->latitude,
                    (float) $o->longitude,
                    (float) $d->latitude,
                    (float) $d->longitude,
                    is_array($route->sea_path) ? $route->sea_path : null
                );

                return [
                    'id' => $route->id,
                    'path' => array_map(static fn (array $p) => [$p[0], $p[1]], $path),
                ];
            })
            ->filter()
            ->values();

        $activeRentalStatuses = ['approved', 'scheduled', 'in_progress', 'active'];
        $imminentDays = max(0, (int) config('logistics_map.imminent_start_horizon_days', 60));
        $latestAllowedStart = $now->addDays($imminentDays);

        $rentals = Rental::query()
            ->where('user_id', $userId)
            // Hide ended rentals; allow null start, past starts, and imminent future starts (see logistics_map.imminent_start_horizon_days).
            // Status can lag behind (e.g. still "approved"/"scheduled") even when the session is already in progress.
            ->whereRaw('LOWER(rentals.status) IN (?,?,?,?)', $activeRentalStatuses)
            ->where(function ($q) use ($latestAllowedStart) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $latestAllowedStart);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->with([
                'container:id,serial_number',
                'route:id,sea_path,origin_port_id,destination_port_id',
                'route.originPort:id,name,latitude,longitude',
                'route.destinationPort:id,name,latitude,longitude',
                'originPort:id,name,latitude,longitude',
                'destinationPort:id,name,latitude,longitude',
            ])
            // Do not cap: a user can have many historical rows; the date/status filters already bound the set.
            // A low LIMIT + ORDER BY updated_at hid legitimately active rentals (e.g. id #5) when newer rows existed.
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $rentalIds = $rentals->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
        $latestShipmentByRentalId = $rentalIds === []
            ? []
            : DB::table('shipment_items')
                ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
                ->whereIn('shipment_items.rental_id', $rentalIds)
                ->orderByDesc('shipments.updated_at')
                ->get([
                    'shipment_items.rental_id',
                    'shipments.status',
                    'shipments.actual_arrival_date',
                    'shipments.port_operations_until',
                    'shipments.departure_date',
                    'shipments.actual_departure_date',
                    'shipments.arrival_date',
                    'shipments.route_id',
                ])
                ->groupBy('rental_id')
                ->map(fn ($rows) => $rows->first())
                ->all();

        /** @var array<int, ShippingRoute|null> */
        $routeByIdCache = [];

        $positions = [];
        foreach ($rentals as $rental) {
            $originPort = $rental->originPort ?? $rental->route?->originPort;
            $destinationPort = $rental->destinationPort ?? $rental->route?->destinationPort;

            $shipRow = $latestShipmentByRentalId[(int) $rental->id] ?? null;
            $postArrival = false;
            if ($shipRow) {
                $portUntil = $shipRow->port_operations_until ? CarbonImmutable::parse((string) $shipRow->port_operations_until) : null;
                $postArrival = $portUntil !== null && $portUntil->lte($now);
            }

            $snapshot = null;
            if ($rental->container_id) {
                $snapshot = ContainerSimulationSnapshot::query()
                    ->where('container_id', $rental->container_id)
                    ->first();
            }
            $ss = is_array($snapshot?->sensor_state) ? $snapshot->sensor_state : [];
            $lat = null;
            $lng = null;
            if ($postArrival && $destinationPort?->latitude !== null && $destinationPort?->longitude !== null) {
                $lat = (float) $destinationPort->latitude;
                $lng = (float) $destinationPort->longitude;
            } else {
                $lat = isset($ss['route_lat']) ? (float) $ss['route_lat'] : null;
                $lng = isset($ss['route_lng']) ? (float) $ss['route_lng'] : null;
            }

            // When telemetry is missing, keep the container on the same sea-leg timeline as the vessel (shipment dates).
            if ((! $postArrival) && ($lat === null || $lng === null) && $shipRow !== null) {
                $depLeg = null;
                foreach (['actual_departure_date', 'departure_date'] as $depCol) {
                    if (! empty($shipRow->{$depCol})) {
                        $depLeg = CarbonImmutable::parse((string) $shipRow->{$depCol});
                        break;
                    }
                }
                $arrLeg = null;
                foreach (['actual_arrival_date', 'arrival_date'] as $arrCol) {
                    if (! empty($shipRow->{$arrCol})) {
                        $arrLeg = CarbonImmutable::parse((string) $shipRow->{$arrCol});
                        break;
                    }
                }
                if ($depLeg !== null && $arrLeg !== null && $arrLeg->gt($depLeg)) {
                    $tShip = LogisticsMapGeometryService::seaLegProgress($depLeg, $arrLeg, $now);
                    if ($tShip !== null) {
                        $shipRouteId = (int) ($shipRow->route_id ?? 0);
                        $routeForPath = null;
                        if ($shipRouteId > 0 && (int) $rental->route_id === $shipRouteId) {
                            $routeForPath = $rental->route;
                        } elseif ($shipRouteId > 0) {
                            if (! array_key_exists($shipRouteId, $routeByIdCache)) {
                                $routeByIdCache[$shipRouteId] = ShippingRoute::query()
                                    ->with(['originPort:id,latitude,longitude', 'destinationPort:id,latitude,longitude'])
                                    ->find($shipRouteId);
                            }
                            $routeForPath = $routeByIdCache[$shipRouteId];
                        } else {
                            $routeForPath = $rental->route;
                        }
                        $oP = $routeForPath?->originPort ?? $originPort;
                        $dP = $routeForPath?->destinationPort ?? $destinationPort;
                        if ($oP?->latitude !== null && $oP?->longitude !== null
                            && $dP?->latitude !== null && $dP?->longitude !== null) {
                            $seaPath = is_array($routeForPath?->sea_path) ? $routeForPath->sea_path : (is_array($rental->route?->sea_path) ? $rental->route->sea_path : null);
                            $path = LogisticsMapGeometryService::resolvePath(
                                (float) $oP->latitude,
                                (float) $oP->longitude,
                                (float) $dP->latitude,
                                (float) $dP->longitude,
                                $seaPath
                            );
                            $pt = LogisticsMapGeometryService::interpolateAlongPath($path, $tShip);
                            $lat = $pt['lat'];
                            $lng = $pt['lng'];
                        }
                    }
                }
            }

            // Fallback: interpolate along the route by rental window when shipment leg / telemetry unavailable.
            if ($lat === null
                && $originPort?->latitude !== null
                && $originPort?->longitude !== null
                && $destinationPort?->latitude !== null
                && $destinationPort?->longitude !== null) {
                $start = $rental->start_date ? CarbonImmutable::parse((string) $rental->start_date) : null;
                $end = $rental->end_date ? CarbonImmutable::parse((string) $rental->end_date) : null;
                $t = 0.0;
                if ($start && $end && $end->gt($start)) {
                    if ($now->lte($start)) {
                        $t = 0.0;
                    } elseif ($now->gte($end)) {
                        $t = 1.0;
                    } else {
                        $total = max(1, $start->diffInSeconds($end));
                        $elapsed = $start->diffInSeconds($now);
                        $t = min(1.0, max(0.0, $elapsed / $total));
                    }
                }

                $seaPath = is_array($rental->route?->sea_path) ? $rental->route->sea_path : null;
                $path = LogisticsMapGeometryService::resolvePath(
                    (float) $originPort->latitude,
                    (float) $originPort->longitude,
                    (float) $destinationPort->latitude,
                    (float) $destinationPort->longitude,
                    $seaPath
                );
                $pt = LogisticsMapGeometryService::interpolateAlongPath($path, $t);
                $lat = $pt['lat'];
                $lng = $pt['lng'];
            }

            // Never emit a position without coordinates when at least one port is known (Leaflet skips null lat/lng).
            if ($lat === null || $lng === null) {
                if ($destinationPort?->latitude !== null && $destinationPort?->longitude !== null) {
                    $lat = (float) $destinationPort->latitude;
                    $lng = (float) $destinationPort->longitude;
                } elseif ($originPort?->latitude !== null && $originPort?->longitude !== null) {
                    $lat = (float) $originPort->latitude;
                    $lng = (float) $originPort->longitude;
                }
            }

            $positions[] = [
                'rental_id' => $rental->id,
                'container_serial' => $rental->container?->serial_number,
                'origin' => $originPort ? [
                    'name' => $originPort->name,
                    'latitude' => $originPort->latitude !== null ? (float) $originPort->latitude : null,
                    'longitude' => $originPort->longitude !== null ? (float) $originPort->longitude : null,
                ] : null,
                'destination' => $destinationPort ? [
                    'name' => $destinationPort->name,
                    'latitude' => $destinationPort->latitude !== null ? (float) $destinationPort->latitude : null,
                    'longitude' => $destinationPort->longitude !== null ? (float) $destinationPort->longitude : null,
                ] : null,
                'latitude' => $lat,
                'longitude' => $lng,
                'logistics_phase' => isset($ss['logistics_phase']) ? (string) $ss['logistics_phase'] : null,
            ];
        }

        $rentalCargoStatuses = ['approved', 'scheduled', 'in_progress', 'active'];
        $rentalCargoStatusSet = array_flip($rentalCargoStatuses);

        /** @var array<int, true> $mapRentalIdSet Rentals already shown as container markers (same window/status rules). */
        $mapRentalIdSet = array_fill_keys($rentalIds, true);

        // Include common operational statuses and anything tied to the user's map-active rentals so vessels are not
        // dropped when status is e.g. "arrived" (see SimulationService) or other legacy labels still linked to the leg.
        $shipmentCoreStatuses = ['scheduled', 'in_progress', 'in_transit', 'arrived'];
        $shipmentStatusPlaceholders = implode(',', array_fill(0, count($shipmentCoreStatuses), '?'));

        $shipments = Shipment::query()
            ->where(function ($outer) use ($shipmentCoreStatuses, $shipmentStatusPlaceholders, $rentalIds) {
                $outer->whereRaw('LOWER(shipments.status) IN ('.$shipmentStatusPlaceholders.')', $shipmentCoreStatuses);
                if ($rentalIds !== []) {
                    $outer->orWhereHas('items', static function ($iq) use ($rentalIds) {
                        $iq->whereIn('rental_id', $rentalIds);
                    });
                }
            })
            ->when($allowedPortIds !== [], function ($q) use ($allowedPortIds, $userId) {
                $q->where(function ($inner) use ($allowedPortIds, $userId) {
                    $inner->whereHas('route', function ($rq) use ($allowedPortIds) {
                        $rq->whereIn('origin_port_id', $allowedPortIds)
                            ->whereIn('destination_port_id', $allowedPortIds);
                    })->orWhereHas('items.rental', function ($rq) use ($userId) {
                        // Always include shipments that carry the user's rentals, even if ports are outside whitelist.
                        $rq->where('rentals.user_id', $userId);
                    });
                });
            })
            ->with([
                'vessel:id,name',
                'route:id,origin_port_id,destination_port_id,sea_path',
                'route.originPort:id,name,latitude,longitude',
                'route.destinationPort:id,name,latitude,longitude',
                'items.rental:id,user_id,status,start_date,end_date',
            ])
            ->get();

        $shipmentPriority = static function (string $status): int {
            $s = strtolower($status);

            return match ($s) {
                'in_transit', 'in_progress' => 2,
                default => 1,
            };
        };

        /** @var array<int, array{shipment: Shipment, priority: int}> $bestByVessel */
        $bestByVessel = [];
        foreach ($shipments as $shipment) {
            $vid = (int) $shipment->vessel_id;
            $priority = $shipmentPriority(strtolower((string) $shipment->status));
            $existing = $bestByVessel[$vid] ?? null;
            if ($existing === null) {
                $bestByVessel[$vid] = ['shipment' => $shipment, 'priority' => $priority];

                continue;
            }
            if ($priority > $existing['priority']) {
                $bestByVessel[$vid] = ['shipment' => $shipment, 'priority' => $priority];

                continue;
            }
            if ($priority === $existing['priority']
                && $shipment->updated_at !== null
                && ($existing['shipment']->updated_at === null || $shipment->updated_at->gt($existing['shipment']->updated_at))) {
                $bestByVessel[$vid] = ['shipment' => $shipment, 'priority' => $priority];
            }
        }

        $vesselPositions = [];
        foreach ($bestByVessel as $entry) {
            /** @var Shipment $shipment */
            $shipment = $entry['shipment'];
            $route = $shipment->route;
            $o = $route?->originPort;
            $d = $route?->destinationPort;
            if ($o?->latitude === null || $o?->longitude === null
                || $d?->latitude === null || $d?->longitude === null) {
                continue;
            }

            $cargoItems = $shipment->items->filter(function ($item) use ($rentalCargoStatusSet) {
                if ($item->rental_id === null || $item->rental === null) {
                    return false;
                }

                $st = strtolower((string) $item->rental->status);

                return isset($rentalCargoStatusSet[$st]);
            });
            $rentalCargoCount = $cargoItems->count();
            $hasRentalCargo = $rentalCargoCount > 0;

            // Same rental IDs as container markers: if the rental is on the map for this user, this leg is "yours".
            $isUserShipment = $shipment->items->contains(function ($item) use ($mapRentalIdSet) {
                return $item->rental_id !== null && isset($mapRentalIdSet[(int) $item->rental_id]);
            });

            $oLat = (float) $o->latitude;
            $oLng = (float) $o->longitude;
            $dLat = (float) $d->latitude;
            $dLng = (float) $d->longitude;

            $depart = $shipment->actual_departure_date ?? $shipment->departure_date;
            $arrive = $shipment->arrival_date;

            $t = 0.0;
            if ($depart !== null && $arrive !== null) {
                $progress = LogisticsMapGeometryService::seaLegProgress($depart, $arrive, $now);
                $t = $progress === null ? 0.0 : $progress;
            }

            $path = LogisticsMapGeometryService::resolvePath(
                $oLat,
                $oLng,
                $dLat,
                $dLng,
                is_array($route->sea_path) ? $route->sea_path : null
            );
            $coords = LogisticsMapGeometryService::interpolateAlongPath($path, $t);

            $vesselPositions[] = [
                'shipment_id' => $shipment->id,
                'vessel_id' => $shipment->vessel_id,
                'vessel_name' => $shipment->vessel?->name ?? 'Vessel',
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'is_user_shipment' => $isUserShipment,
                'has_rental_cargo' => $hasRentalCargo,
                'rental_cargo_count' => $rentalCargoCount,
                'shipment_status' => (string) $shipment->status,
                'origin_name' => $o->name,
                'destination_name' => $d->name,
            ];
        }

        $usedVesselIds = array_values(array_unique(array_map(
            static fn (array $row) => (int) $row['vessel_id'],
            $vesselPositions
        )));

        $appendAtPortVessel = static function (Vessel $idleVessel) use (&$vesselPositions): void {
            $port = $idleVessel->currentPort;
            if ($port?->latitude === null || $port?->longitude === null) {
                return;
            }

            $vesselPositions[] = [
                'shipment_id' => null,
                'vessel_id' => $idleVessel->id,
                'vessel_name' => $idleVessel->name,
                'latitude' => (float) $port->latitude,
                'longitude' => (float) $port->longitude,
                'is_user_shipment' => false,
                'has_rental_cargo' => false,
                'rental_cargo_count' => 0,
                'shipment_status' => 'at_port',
                'origin_name' => $port->name,
                'destination_name' => null,
            ];
        };

        $idleQuery = Vessel::query()
            ->whereNotNull('current_port_id')
            ->when($usedVesselIds !== [], fn ($q) => $q->whereNotIn('id', $usedVesselIds))
            ->with(['currentPort:id,name,latitude,longitude,city']);

        foreach ($idleQuery->get() as $idleVessel) {
            $appendAtPortVessel($idleVessel);
        }

        $placedVesselIds = array_values(array_unique(array_map(
            static fn (array $row) => (int) $row['vessel_id'],
            $vesselPositions
        )));

        $missingVessels = Vessel::query()
            ->whereNotNull('current_port_id')
            ->when($placedVesselIds !== [], fn ($q) => $q->whereNotIn('id', $placedVesselIds))
            ->with(['currentPort:id,name,latitude,longitude,city'])
            ->get();

        foreach ($missingVessels as $vessel) {
            $appendAtPortVessel($vessel);
        }

        return response()->json([
            'ports' => $ports,
            'route_edges' => $routeEdges,
            'vessel_positions' => $vesselPositions,
            'positions' => $positions,
        ]);
    }
}
