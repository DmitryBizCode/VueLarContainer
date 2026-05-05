<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shipment;
use App\Models\Vessel;
use Carbon\CarbonImmutable;

class VesselPortScheduleService
{
    /**
     * Whether at least one vessel can load at the origin port at or after the given time.
     */
    public function hasAssignableVesselAtOrigin(int $originPortId, CarbonImmutable $readyFrom): bool
    {
        $operational = config('logistics.vessel_operational_statuses', ['active', 'in_transit', 'in_port', 'scheduled']);

        return Vessel::query()
            ->where('current_port_id', $originPortId)
            ->whereIn('status', $operational)
            ->where(function ($q) use ($readyFrom) {
                $q->whereNull('out_of_service_until')
                    ->orWhere('out_of_service_until', '<=', $readyFrom);
            })
            ->where(function ($q) use ($readyFrom) {
                $q->whereNull('berth_busy_until')
                    ->orWhere('berth_busy_until', '<=', $readyFrom);
            })
            ->exists();
    }

    public function isVesselOperational(?Vessel $vessel): bool
    {
        if ($vessel === null) {
            return false;
        }

        $operational = config('logistics.vessel_operational_statuses', ['active', 'in_transit', 'in_port', 'scheduled']);

        if (! in_array((string) $vessel->status, $operational, true)) {
            return false;
        }

        if ($vessel->out_of_service_until !== null && $vessel->out_of_service_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Pick the best vessel at the port for a new shipment (earliest berth availability).
     */
    public function pickVesselAtPort(int $originPortId, CarbonImmutable $readyFrom): ?Vessel
    {
        $operational = config('logistics.vessel_operational_statuses', ['active', 'in_transit', 'in_port', 'scheduled']);

        return Vessel::query()
            ->where('current_port_id', $originPortId)
            ->whereIn('status', $operational)
            ->where(function ($q) use ($readyFrom) {
                $q->whereNull('out_of_service_until')
                    ->orWhere('out_of_service_until', '<=', $readyFrom);
            })
            ->where(function ($q) use ($readyFrom) {
                $q->whereNull('berth_busy_until')
                    ->orWhere('berth_busy_until', '<=', $readyFrom);
            })
            ->orderBy('berth_busy_until')
            ->orderBy('id')
            ->first();
    }

    /**
     * Earliest datetime a vessel can DEPART from $portId, combining:
     *   a) vessels already at the port (berth_busy_until logic),
     *   b) vessels scheduled to arrive within $forecastDays (via Shipment records).
     * Returns null if no operational vessel is present or forecast.
     */
    public function nextDepartureWindowAtPort(
        int $portId,
        CarbonImmutable $after,
        int $forecastDays
    ): ?CarbonImmutable {
        $portOpsDays = (int) config('logistics.port_operations_min_days', 2);

        $fromCurrent = $this->nextAssignableTimeAtPort($portId, $after);

        $horizon = $after->addDays($forecastDays);
        $operational = config('logistics.vessel_operational_statuses', ['active', 'in_transit', 'in_port', 'scheduled']);

        $arrivalTs = Shipment::query()
            ->join('routes', 'routes.id', '=', 'shipments.route_id')
            ->join('vessels', 'vessels.id', '=', 'shipments.vessel_id')
            ->where('routes.destination_port_id', $portId)
            ->whereIn('shipments.status', ['scheduled', 'in_transit'])
            ->whereIn('vessels.status', $operational)
            ->where('shipments.arrival_date', '>', $after)
            ->where('shipments.arrival_date', '<=', $horizon)
            ->orderBy('shipments.arrival_date')
            ->value('shipments.arrival_date');

        $fromIncoming = $arrivalTs !== null
            ? CarbonImmutable::parse((string) $arrivalTs)->addDays($portOpsDays)
            : null;

        if ($fromCurrent === null) {
            return $fromIncoming;
        }
        if ($fromIncoming === null) {
            return $fromCurrent;
        }

        return $fromCurrent->lt($fromIncoming) ? $fromCurrent : $fromIncoming;
    }

    /**
     * Compute the earliest time a vessel can be assigned at this port.
     * Returns null when no operational vessel exists at the port at all.
     */
    public function nextAssignableTimeAtPort(int $originPortId, CarbonImmutable $readyFrom): ?CarbonImmutable
    {
        $operational = config('logistics.vessel_operational_statuses', ['active', 'in_transit', 'in_port', 'scheduled']);

        $vessels = Vessel::query()
            ->where('current_port_id', $originPortId)
            ->whereIn('status', $operational)
            ->get(['id', 'berth_busy_until', 'out_of_service_until']);

        if ($vessels->isEmpty()) {
            return null;
        }

        $best = null;
        foreach ($vessels as $vessel) {
            $t = $readyFrom;
            if ($vessel->out_of_service_until !== null && $vessel->out_of_service_until->gt($t)) {
                $t = CarbonImmutable::instance($vessel->out_of_service_until);
            }
            if ($vessel->berth_busy_until !== null && $vessel->berth_busy_until->gt($t)) {
                $t = CarbonImmutable::instance($vessel->berth_busy_until);
            }
            if ($best === null || $t->lt($best)) {
                $best = $t;
            }
        }

        return $best;
    }
}
