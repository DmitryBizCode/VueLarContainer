<?php

declare(strict_types=1);

namespace App\Services;

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
