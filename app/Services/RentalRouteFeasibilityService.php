<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Port;
use Carbon\CarbonImmutable;

class RentalRouteFeasibilityService
{
    public function __construct(
        private readonly VesselPortScheduleService $vesselSchedule
    ) {}

    /**
     * @param  list<array{route_id:int,origin_port_id:int,destination_port_id:int,estimated_days:int,distance:float}>  $legs
     * @return array{
     *   can_create_rental: bool,
     *   strategy: string|null,
     *   route_type: string,
     *   segments: list<array{
     *     order:int,
     *     route_id:int,
     *     from_port_id:int,
     *     to_port_id:int,
     *     from_port_name:string|null,
     *     to_port_name:string|null,
     *     vessel: array{id:int,name:string,status:string|null,capacity_teu:int|null}|null,
     *     planned_departure: string|null,
     *     planned_arrival: string|null,
     *     travel_duration_hours:int,
     *     waiting_time_before_departure_hours:int,
     *     waiting_time_after_arrival_hours:int
     *   }>,
     *   total_travel_time_hours:int,
     *   total_waiting_time_hours:int,
     *   minimum_rental_days:int,
     *   recommended_rental_days:int,
     *   warnings: list<string>,
     *   hints: list<string>
     * }
     */
    public function buildPlan(array $legs, CarbonImmutable $startDate, ?string $strategy = null): array
    {
        $warnings = [];
        $hints = [];
        $segments = [];

        $portOpsMinDays = max(0, (int) config('logistics.port_operations_min_days', 2));
        $postArrivalMinDays = max(0, (int) config('logistics.post_arrival_min_days', 1));
        $maxProxyWaitHours = max(0, (int) config('logistics_map.max_proxy_wait_hours', 168)); // default 7 days

        $routeType = count($legs) > 1 ? 'indirect' : 'direct';
        if ($routeType === 'direct') {
            $hints[] = 'Direct route is available.';
        } else {
            $hints[] = 'Indirect route is available with transshipment.';
        }

        // Resolve port names for nicer UI payload.
        $portIds = [];
        foreach ($legs as $leg) {
            $portIds[(int) $leg['origin_port_id']] = true;
            $portIds[(int) $leg['destination_port_id']] = true;
        }
        $portNameById = $portIds === []
            ? []
            : Port::query()->whereIn('id', array_keys($portIds))->pluck('name', 'id')->all();

        $readyFrom = $startDate;
        $totalTravelHours = 0;
        $totalWaitingHours = 0;

        foreach ($legs as $i => $leg) {
            $order = $i + 1;
            $originPortId = (int) $leg['origin_port_id'];
            $destinationPortId = (int) $leg['destination_port_id'];
            $estimatedDays = max(0, (int) $leg['estimated_days']);

            $nextAssignableAt = $this->vesselSchedule->nextAssignableTimeAtPort($originPortId, $readyFrom);
            if ($nextAssignableAt === null) {
                $warnings[] = "No operational vessel is available at origin port for leg {$order}.";

                return $this->finalize(false, $strategy, $routeType, $segments, $totalTravelHours, $totalWaitingHours, $warnings, $hints);
            }

            $waitBeforeHours = max(0, (int) ceil($readyFrom->diffInSeconds($nextAssignableAt, false) / 3600));
            if ($waitBeforeHours > 0) {
                $totalWaitingHours += $waitBeforeHours;
            }

            if ($waitBeforeHours > $maxProxyWaitHours) {
                $warnings[] = "Waiting time before departure for leg {$order} is too long ({$waitBeforeHours} hours).";

                return $this->finalize(false, $strategy, $routeType, $segments, $totalTravelHours, $totalWaitingHours, $warnings, $hints);
            }

            $departure = $nextAssignableAt;
            $arrival = $departure->addDays($estimatedDays);
            $travelHours = $estimatedDays * 24;
            $totalTravelHours += $travelHours;

            $vessel = $this->vesselSchedule->pickVesselAtPort($originPortId, $departure);
            $vesselOut = $vessel === null ? null : [
                'id' => (int) $vessel->id,
                'name' => (string) $vessel->name,
                'status' => $vessel->status !== null ? (string) $vessel->status : null,
                'capacity_teu' => $vessel->capacity_teu !== null ? (int) $vessel->capacity_teu : null,
            ];

            $waitAfterHours = 0;
            if ($i < count($legs) - 1) {
                $waitAfterHours = $portOpsMinDays * 24;
                $totalWaitingHours += $waitAfterHours;
            }

            $segments[] = [
                'order' => $order,
                'route_id' => (int) $leg['route_id'],
                'from_port_id' => $originPortId,
                'to_port_id' => $destinationPortId,
                'from_port_name' => $portNameById[$originPortId] ?? null,
                'to_port_name' => $portNameById[$destinationPortId] ?? null,
                'vessel' => $vesselOut,
                'planned_departure' => $departure->toIso8601String(),
                'planned_arrival' => $arrival->toIso8601String(),
                'travel_duration_hours' => $travelHours,
                'waiting_time_before_departure_hours' => $waitBeforeHours,
                'waiting_time_after_arrival_hours' => $waitAfterHours,
            ];

            $readyFrom = $arrival->addDays($i < count($legs) - 1 ? $portOpsMinDays : 0);
        }

        $finalArrival = $segments !== []
            ? CarbonImmutable::parse((string) $segments[count($segments) - 1]['planned_arrival'])
            : $startDate;
        $minEnd = $finalArrival->addDays($postArrivalMinDays);
        $minDays = max(1, (int) ceil($startDate->diffInSeconds($minEnd) / 86400));
        $recommended = $minDays + 2;

        if ($totalWaitingHours > 0) {
            $hints[] = "This routing includes waiting/handling time at ports (".$totalWaitingHours." hours).";
        }

        return [
            'can_create_rental' => true,
            'strategy' => $strategy,
            'route_type' => $routeType,
            'segments' => $segments,
            'total_travel_time_hours' => $totalTravelHours,
            'total_waiting_time_hours' => $totalWaitingHours,
            'minimum_rental_days' => $minDays,
            'recommended_rental_days' => $recommended,
            'warnings' => $warnings,
            'hints' => $hints,
        ];
    }

    /**
     * @param list<array<string,mixed>> $segments
     * @param list<string> $warnings
     * @param list<string> $hints
     * @return array<string,mixed>
     */
    private function finalize(
        bool $canCreate,
        ?string $strategy,
        string $routeType,
        array $segments,
        int $totalTravelHours,
        int $totalWaitingHours,
        array $warnings,
        array $hints
    ): array {
        $minimumDays = max(0, (int) ceil(($totalTravelHours + $totalWaitingHours) / 24));
        $recommended = $minimumDays > 0 ? $minimumDays + 2 : 0;

        return [
            'can_create_rental' => $canCreate,
            'strategy' => $strategy,
            'route_type' => $routeType,
            'segments' => $segments,
            'total_travel_time_hours' => $totalTravelHours,
            'total_waiting_time_hours' => $totalWaitingHours,
            'minimum_rental_days' => $minimumDays,
            'recommended_rental_days' => $recommended,
            'warnings' => $warnings,
            'hints' => $hints,
        ];
    }
}

