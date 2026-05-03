<?php

namespace App\Http\Controllers;

use App\Models\ContainerSimulationSnapshot;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\LogisticsMapGeometryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogisticsMapDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
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

                // Only draw route edges that have real sea geometry (at least 1 waypoint).
                $seaPath = is_array($route->sea_path) ? $route->sea_path : null;
                if (! is_array($seaPath) || count($seaPath) < 1) {
                    return null;
                }

                // Skip drawing routes that lack stored sea_path geometry for non-trivial distances —
                // preferring no line to a misleading land-crossing polyline.
                $path = LogisticsMapGeometryService::resolvePathIfNavigable(
                    (float) $o->latitude,
                    (float) $o->longitude,
                    (float) $d->latitude,
                    (float) $d->longitude,
                    $seaPath
                );

                if ($path === null) {
                    return null;
                }

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
            // User-only visibility on non-admin map endpoints.
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
            // Approved/scheduled rentals only appear once payment is confirmed; in_progress/active are already live.
            ->where(function ($q) {
                $q->whereIn('status', ['in_progress', 'active'])
                    ->orWhere('payment_status', 'paid');
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
            : ShipmentItem::query()
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

        // Preload port names for every port referenced by a rental's route_legs — used to build human-readable transshipment popups.
        $legPortIds = [];
        foreach ($rentals as $rental) {
            $pb = is_array($rental->price_breakdown) ? $rental->price_breakdown : [];
            foreach (is_array($pb['route_legs'] ?? null) ? $pb['route_legs'] : [] as $leg) {
                if (! empty($leg['origin_port_id'])) {
                    $legPortIds[(int) $leg['origin_port_id']] = true;
                }
                if (! empty($leg['destination_port_id'])) {
                    $legPortIds[(int) $leg['destination_port_id']] = true;
                }
            }
        }
        $portNameById = $legPortIds === []
            ? []
            : Port::query()->whereIn('id', array_keys($legPortIds))->pluck('name', 'id')->all();

        $positions = [];
        $rentalRouteSegments = [];
        foreach ($rentals as $rental) {
            $originPort = $rental->originPort ?? $rental->route?->originPort;
            $destinationPort = $rental->destinationPort ?? $rental->route?->destinationPort;

            $shipRow = $latestShipmentByRentalId[(int) $rental->id] ?? null;
            $postArrival = false;
            if ($shipRow) {
                $portUntil = $shipRow->port_operations_until ? CarbonImmutable::parse((string) $shipRow->port_operations_until) : null;
                $postArrival = $portUntil !== null && $portUntil->lte($now);
            }

            $rentalStart = $rental->start_date ? CarbonImmutable::parse((string) $rental->start_date) : null;
            $shippingPhase = 'at_port';
            if ($postArrival) {
                $shippingPhase = 'post_arrival';
            } elseif ($rentalStart !== null && $now->lt($rentalStart)) {
                $shippingPhase = 'pre_departure';
            } elseif ($shipRow !== null) {
                $shipStatus = strtolower((string) ($shipRow->status ?? ''));
                if (in_array($shipStatus, ['in_transit', 'in_progress'], true)) {
                    $shippingPhase = 'in_transit';
                } elseif ($shipStatus === 'arrived') {
                    $shippingPhase = 'at_destination';
                } elseif ($shipStatus === 'scheduled') {
                    $shippingPhase = 'pre_departure';
                }
            } elseif ($rentalStart !== null && $now->gte($rentalStart)) {
                $shippingPhase = 'in_transit';
            }

            $priceBreakdown = is_array($rental->price_breakdown) ? $rental->price_breakdown : [];
            $rawLegs = is_array($priceBreakdown['route_legs'] ?? null) ? $priceBreakdown['route_legs'] : [];
            $legCount = count($rawLegs);
            $isMultiHop = $legCount > 1;

            $snapshot = null;
            if ($rental->container_id) {
                $snapshot = ContainerSimulationSnapshot::query()
                    ->where('container_id', $rental->container_id)
                    ->first();
            }
            $ss = is_array($snapshot?->sensor_state) ? $snapshot->sensor_state : [];
            $lat = null;
            $lng = null;

            // Lifecycle pinning: pre_departure sits at origin, post_arrival/at_destination at destination.
            // Keeps the container marker from drifting toward destination while a rental is still approved/paid but not yet sailing.
            $pinOrigin = in_array($shippingPhase, ['pre_departure', 'at_port'], true)
                && $originPort?->latitude !== null && $originPort?->longitude !== null;
            $pinDestination = in_array($shippingPhase, ['at_destination', 'post_arrival'], true)
                && $destinationPort?->latitude !== null && $destinationPort?->longitude !== null;

            if ($pinDestination) {
                $lat = (float) $destinationPort->latitude;
                $lng = (float) $destinationPort->longitude;
            } elseif ($pinOrigin) {
                $lat = (float) $originPort->latitude;
                $lng = (float) $originPort->longitude;
            } else {
                $lat = isset($ss['route_lat']) ? (float) $ss['route_lat'] : null;
                $lng = isset($ss['route_lng']) ? (float) $ss['route_lng'] : null;
            }

            // When telemetry is missing, keep the container on the same sea-leg timeline as the vessel (shipment dates).
            if ((! $pinDestination) && (! $pinOrigin) && ($lat === null || $lng === null) && $shipRow !== null) {
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
                            $path = LogisticsMapGeometryService::resolvePathIfNavigable(
                                (float) $oP->latitude,
                                (float) $oP->longitude,
                                (float) $dP->latitude,
                                (float) $dP->longitude,
                                $seaPath
                            );
                            if ($path !== null) {
                                $pt = LogisticsMapGeometryService::interpolateAlongPath($path, $tShip);
                                $lat = $pt['lat'];
                                $lng = $pt['lng'];
                            } else {
                                // No navigable geometry: keep the marker at the last known port (origin) until geometry is configured.
                                $lat = (float) $oP->latitude;
                                $lng = (float) $oP->longitude;
                            }
                        }
                    }
                }
            }

            // Fallback: interpolate along the route by rental window when shipment leg / telemetry unavailable.
            if ($lat === null
                && (! $pinDestination) && (! $pinOrigin)
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
                $path = LogisticsMapGeometryService::resolvePathIfNavigable(
                    (float) $originPort->latitude,
                    (float) $originPort->longitude,
                    (float) $destinationPort->latitude,
                    (float) $destinationPort->longitude,
                    $seaPath
                );
                if ($path !== null) {
                    $pt = LogisticsMapGeometryService::interpolateAlongPath($path, $t);
                    $lat = $pt['lat'];
                    $lng = $pt['lng'];
                } else {
                    $lat = (float) $originPort->latitude;
                    $lng = (float) $originPort->longitude;
                }
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

            // While the vessel is underway for this rental, the container marker is suppressed on the client —
            // the vessel circle takes over as the sole moving indicator. The coord is still emitted for safe fallback.
            $onVessel = $shippingPhase === 'in_transit';

            $routeLegs = [];
            foreach ($rawLegs as $leg) {
                if (! is_array($leg)) {
                    continue;
                }
                $oid = isset($leg['origin_port_id']) ? (int) $leg['origin_port_id'] : null;
                $did = isset($leg['destination_port_id']) ? (int) $leg['destination_port_id'] : null;
                $routeLegs[] = [
                    'route_id' => isset($leg['route_id']) ? (int) $leg['route_id'] : null,
                    'origin_port_id' => $oid,
                    'destination_port_id' => $did,
                    'origin_name' => $oid !== null ? ($portNameById[$oid] ?? null) : null,
                    'destination_name' => $did !== null ? ($portNameById[$did] ?? null) : null,
                    'estimated_days' => isset($leg['estimated_days']) ? (int) $leg['estimated_days'] : null,
                    'distance' => isset($leg['distance']) ? (float) $leg['distance'] : null,
                ];
            }

            // Determine the active leg: if the shipment's route_id matches one of the legs, that's the current leg.
            $currentLegIndex = null;
            if ($shipRow !== null && ! empty($shipRow->route_id)) {
                $shipRouteId = (int) $shipRow->route_id;
                foreach ($routeLegs as $i => $leg) {
                    if ((int) ($leg['route_id'] ?? 0) === $shipRouteId) {
                        $currentLegIndex = $i;
                        break;
                    }
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
                'shipping_phase' => $shippingPhase,
                'rental_status' => (string) $rental->status,
                'payment_status' => (string) $rental->payment_status,
                'is_multi_hop' => $isMultiHop,
                'leg_count' => $legCount,
                'route_legs' => $routeLegs,
                'current_leg_index' => $currentLegIndex,
                'on_vessel' => $onVessel,
            ];

            // Build per-rental multi-leg route segments for map overlay (current/completed/upcoming).
            $legRouteIds = array_values(array_unique(array_filter(array_map(
                static fn (array $leg) => isset($leg['route_id']) ? (int) $leg['route_id'] : null,
                $routeLegs
            ))));
            if ($legRouteIds !== []) {
                $legModels = ShippingRoute::query()
                    ->whereIn('id', $legRouteIds)
                    ->with(['originPort:id,latitude,longitude', 'destinationPort:id,latitude,longitude'])
                    ->get()
                    ->keyBy('id');

                foreach ($routeLegs as $i => $leg) {
                    $rid = (int) ($leg['route_id'] ?? 0);
                    $m = $rid > 0 ? ($legModels[$rid] ?? null) : null;
                    $oP = $m?->originPort;
                    $dP = $m?->destinationPort;
                    if ($oP?->latitude === null || $oP?->longitude === null || $dP?->latitude === null || $dP?->longitude === null) {
                        continue;
                    }
                    $path = LogisticsMapGeometryService::resolvePathIfNavigable(
                        (float) $oP->latitude,
                        (float) $oP->longitude,
                        (float) $dP->latitude,
                        (float) $dP->longitude,
                        is_array($m?->sea_path) ? $m->sea_path : null
                    );
                    if ($path === null) {
                        continue;
                    }

                    $segmentState = 'upcoming';
                    if ($currentLegIndex !== null) {
                        if ($i < $currentLegIndex) {
                            $segmentState = 'completed';
                        } elseif ($i === $currentLegIndex) {
                            $segmentState = 'current';
                        }
                    } elseif ($shippingPhase === 'in_transit') {
                        $segmentState = 'current';
                    } elseif (in_array($shippingPhase, ['at_destination', 'post_arrival'], true)) {
                        $segmentState = 'completed';
                    }

                    $rentalRouteSegments[] = [
                        'rental_id' => $rental->id,
                        'leg_index' => $i,
                        'state' => $segmentState,
                        'path' => array_map(static fn (array $p) => [$p[0], $p[1]], $path),
                    ];
                }
            }
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
            // User-only: only shipments carrying this user's rentals — never leak others'.
            ->when($rentalIds === [], function ($q) {
                $q->whereRaw('1 = 0');
            }, function ($q) use ($rentalIds) {
                $q->whereHas('items', static function ($iq) use ($rentalIds) {
                    $iq->whereIn('rental_id', $rentalIds);
                });
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

            $path = LogisticsMapGeometryService::resolvePathIfNavigable(
                $oLat,
                $oLng,
                $dLat,
                $dLng,
                is_array($route->sea_path) ? $route->sea_path : null
            );
            // Without navigable geometry, keep the vessel pinned at the origin berth rather than draw it over land.
            $coords = $path !== null
                ? LogisticsMapGeometryService::interpolateAlongPath($path, $t)
                : ['lat' => $oLat, 'lng' => $oLng];

            $heading = $path !== null ? (int) round(LogisticsMapGeometryService::bearingAlongPath($path, $t)) : 0;

            $vesselPositions[] = [
                'shipment_id' => $shipment->id,
                'vessel_id' => $shipment->vessel_id,
                'vessel_name' => $shipment->vessel?->name ?? 'Vessel',
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'heading' => $heading,
                'is_user_shipment' => $isUserShipment,
                'has_rental_cargo' => $hasRentalCargo,
                'rental_cargo_count' => $rentalCargoCount,
                'shipment_status' => (string) $shipment->status,
                'origin_name' => $o->name,
                'destination_name' => $d->name,
                'arrival_date' => $shipment->arrival_date?->toDateString(),
            ];
        }

        return response()->json([
            'ports' => $ports,
            'route_edges' => $routeEdges,
            'vessel_positions' => $vesselPositions,
            'positions' => $positions,
            'rental_route_segments' => $rentalRouteSegments,
            'is_ops_view' => false,
        ]);
    }
}
