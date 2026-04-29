<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Route;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class ContainerAvailabilityService
{
    public function __construct(
        private readonly RoutePathfinderService $pathfinder,
        private readonly VesselPortScheduleService $vesselSchedule,
    ) {}

    /**
     * @return list<int> IDs of open routes with at least one "available" container at the origin port.
     */
    public function openRouteIdsWithAvailableContainerAtOrigin(): array
    {
        $ids = Route::query()
            ->where('route_status', 'open')
            ->whereExists(function ($q) {
                $q->from('containers')
                    ->whereColumn('containers.current_port_id', 'routes.origin_port_id')
                    ->where('containers.current_status', 'available');
            })
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Ports where at least one container in `available` status is currently located.
     * Used to populate "Origin port" in rental request (Select ports) so users cannot pick a port with no load-out stock.
     *
     * @return list<int>
     */
    public function portIdsWithAvailableContainerAtPort(): array
    {
        return Container::query()
            ->where('current_status', 'available')
            ->whereNotNull('current_port_id')
            ->distinct()
            ->pluck('current_port_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     route_id:int|null,
     *     origin_port_id:int|null,
     *     destination_port_id:int|null,
     *     distance:float,
     *     estimated_days:int,
     *     route_legs: list<array{route_id:int,origin_port_id:int,destination_port_id:int,estimated_days:int,distance:float}>,
     *     routing_mode:string,
     *     path_found:bool,
     *     min_rental_span_days:int
     * }
     */
    public function resolveRouteContext(array $payload): array
    {
        $routingMode = $this->resolveGraphWeightMode($payload);
        $routeMode = $payload['route_mode'] ?? 'route';

        if ($routeMode === 'route' && ! empty($payload['route_id'])) {
            $route = Route::query()->find((int) $payload['route_id']);
            if ($route === null) {
                return $this->emptyContext($routingMode);
            }

            $leg = $this->legFromRouteModel($route);

            return $this->assembleContextFromLegs([$leg], $routingMode, false);
        }

        $originPortId = ! empty($payload['origin_port_id']) ? (int) $payload['origin_port_id'] : null;
        $destinationPortId = ! empty($payload['destination_port_id']) ? (int) $payload['destination_port_id'] : null;

        if ($routeMode === 'ports' && $originPortId && $destinationPortId) {
            $direct = Route::query()
                ->where('route_status', 'open')
                ->where('origin_port_id', $originPortId)
                ->where('destination_port_id', $destinationPortId)
                ->orderBy($routingMode === 'cost' ? 'distance' : 'estimated_days')
                ->first();

            if ($direct !== null) {
                return $this->assembleContextFromLegs([$this->legFromRouteModel($direct)], $routingMode, false);
            }

            $metric = $routingMode === 'cost' ? 'cost' : 'time';
            $path = $this->pathfinder->findPath($originPortId, $destinationPortId, $metric);

            if ($path === null) {
                return [
                    'route_id' => null,
                    'origin_port_id' => $originPortId,
                    'destination_port_id' => $destinationPortId,
                    'distance' => 0.0,
                    'estimated_days' => 0,
                    'route_legs' => [],
                    'routing_mode' => $routingMode,
                    'path_found' => false,
                    'min_rental_span_days' => 0,
                ];
            }

            $legs = $path['legs'];

            return $this->assembleContextFromLegs($legs, $routingMode, $path['multi_hop']);
        }

        return [
            'route_id' => null,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'distance' => 0.0,
            'estimated_days' => 1,
            'route_legs' => [],
            'routing_mode' => $routingMode,
            'path_found' => true,
            'min_rental_span_days' => 0,
        ];
    }

    /**
     * @param  array<int, array{route_id:int,origin_port_id:int,destination_port_id:int,estimated_days:int,distance:float}>  $legs
     */
    private function assembleContextFromLegs(array $legs, string $routingMode, bool $multiHop): array
    {
        if ($legs === []) {
            return [
                'route_id' => null,
                'origin_port_id' => null,
                'destination_port_id' => null,
                'distance' => 0.0,
                'estimated_days' => 0,
                'route_legs' => [],
                'routing_mode' => $routingMode,
                'path_found' => true,
                'min_rental_span_days' => 0,
            ];
        }

        $totalDays = (int) array_sum(array_column($legs, 'estimated_days'));
        $totalDistance = (float) array_sum(array_column($legs, 'distance'));
        $spanDays = $totalDays
            + (int) config('logistics.port_operations_max_days', 4)
            + (int) config('logistics.post_arrival_min_days', 1);

        $first = $legs[0];
        $last = $legs[array_key_last($legs)];

        return [
            'route_id' => (int) $first['route_id'],
            'origin_port_id' => (int) $first['origin_port_id'],
            'destination_port_id' => (int) $last['destination_port_id'],
            'distance' => $totalDistance,
            'estimated_days' => $totalDays,
            'route_legs' => $legs,
            'routing_mode' => $routingMode,
            'path_found' => true,
            'min_rental_span_days' => $spanDays,
            'multi_hop' => $multiHop,
        ];
    }

    /**
     * @return array{route_id:int,origin_port_id:int,destination_port_id:int,estimated_days:int,distance:float}
     */
    private function legFromRouteModel(Route $route): array
    {
        return [
            'route_id' => (int) $route->id,
            'origin_port_id' => (int) $route->origin_port_id,
            'destination_port_id' => (int) $route->destination_port_id,
            'estimated_days' => (int) $route->estimated_days,
            'distance' => (float) $route->distance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(string $routingMode): array
    {
        return [
            'route_id' => null,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'distance' => 0.0,
            'estimated_days' => 1,
            'route_legs' => [],
            'routing_mode' => $routingMode,
            'path_found' => false,
            'min_rental_span_days' => 0,
        ];
    }

    private function resolveGraphWeightMode(array $payload): string
    {
        $routingPriority = $payload['routing_priority'] ?? null;
        if (in_array($routingPriority, ['cost'], true)) {
            return 'cost';
        }
        if (in_array($routingPriority, ['speed'], true)) {
            return 'time';
        }

        $priority = $payload['priority'] ?? 'normal';

        return in_array($priority, ['express', 'urgent'], true) ? 'time' : 'cost';
    }

    /**
     * @param  array<int, string>  $cargoTypes
     */
    public function findAvailableContainers(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?int $originPortId = null,
        ?float $requestedWeight = null,
        array $cargoTypes = []
    ): Collection {
        if (config('logistics.require_vessel_at_origin', true) && $originPortId !== null) {
            if (! $this->vesselSchedule->hasAssignableVesselAtOrigin($originPortId, $startDate)) {
                return Container::query()->whereRaw('1 = 0')->get();
            }
        }

        $buildBaseQuery = function () use ($startDate, $endDate, $originPortId, $requestedWeight): \Illuminate\Database\Eloquent\Builder {
            $query = Container::query()
                ->with(['owner', 'currentPort.country'])
                ->where('current_status', 'available');

            if ($originPortId) {
                $query->where(function ($nested) use ($originPortId) {
                    $nested
                        ->whereNull('current_port_id')
                        ->orWhere('current_port_id', $originPortId);
                });
            }

            if ($requestedWeight !== null && $requestedWeight > 0) {
                $query->where('max_weight', '>=', $requestedWeight);
            }

            $blockingStatuses = ['approved', 'scheduled', 'in_progress'];
            $query->whereDoesntHave('rentals', function ($rentalQuery) use ($startDate, $endDate, $blockingStatuses) {
                $rentalQuery
                    ->whereIn('status', $blockingStatuses)
                    ->where(function ($dateScope) use ($startDate, $endDate) {
                        $dateScope
                            ->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($deepScope) use ($startDate, $endDate) {
                                $deepScope
                                    ->where('start_date', '<=', $startDate)
                                    ->where(function ($openEndedScope) use ($endDate) {
                                        $openEndedScope
                                            ->whereNull('end_date')
                                            ->orWhere('end_date', '>=', $endDate);
                                    });
                            });
                    });
            });

            return $query;
        };

        $baseQuery = $buildBaseQuery();

        $preferredTypes = $this->preferredTypesForCargo($cargoTypes);
        if ($preferredTypes !== []) {
            $preferredQuery = (clone $baseQuery)->whereIn('type', $preferredTypes);

            $preferredContainers = $preferredQuery
                ->orderByDesc('iot_active')
                ->orderBy('max_weight')
                ->limit(50)
                ->get();

            if ($preferredContainers->isNotEmpty()) {
                return $preferredContainers;
            }
        }

        return $baseQuery
            ->orderByDesc('iot_active')
            ->orderBy('max_weight')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<int, string>  $cargoTypes
     */
    private function preferredTypesForCargo(array $cargoTypes): array
    {
        $normalized = collect($cargoTypes)
            ->map(static fn ($type) => strtolower((string) $type))
            ->filter()
            ->values();

        if ($normalized->contains('food')) {
            return ['refrigerated', 'reefer'];
        }

        if ($normalized->contains('machinery')) {
            return ['flat_rack', 'open_top', 'high_cube', 'standard'];
        }

        return [];
    }
}
