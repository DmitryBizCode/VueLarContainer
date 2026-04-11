<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Route;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class ContainerAvailabilityService
{
    /**
     * @return array{route_id:int|null,origin_port_id:int|null,destination_port_id:int|null,distance:float,estimated_days:int}
     */
    public function resolveRouteContext(array $payload): array
    {
        $routeMode = $payload['route_mode'] ?? 'route';

        if ($routeMode === 'route' && ! empty($payload['route_id'])) {
            $route = Route::query()->find((int) $payload['route_id']);

            return [
                'route_id' => $route?->id,
                'origin_port_id' => $route?->origin_port_id,
                'destination_port_id' => $route?->destination_port_id,
                'distance' => (float) ($route?->distance ?? 0),
                'estimated_days' => (int) ($route?->estimated_days ?? 1),
            ];
        }

        $originPortId = ! empty($payload['origin_port_id']) ? (int) $payload['origin_port_id'] : null;
        $destinationPortId = ! empty($payload['destination_port_id']) ? (int) $payload['destination_port_id'] : null;

        $route = null;
        if ($originPortId && $destinationPortId) {
            $route = Route::query()
                ->where('origin_port_id', $originPortId)
                ->where('destination_port_id', $destinationPortId)
                ->orderBy('distance')
                ->first();
        }

        return [
            'route_id' => $route?->id,
            'origin_port_id' => $route?->origin_port_id ?? $originPortId,
            'destination_port_id' => $route?->destination_port_id ?? $destinationPortId,
            'distance' => (float) ($route?->distance ?? 0),
            'estimated_days' => (int) ($route?->estimated_days ?? 1),
        ];
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
     * @return array<int, string>
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
