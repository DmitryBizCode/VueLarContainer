<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shipment;
use App\Models\Vessel;
use Carbon\CarbonImmutable;

/**
 * Advances shipments and their vessels based on scheduled departure / arrival timestamps.
 *
 * Status flow:
 *   scheduled  → in_transit  (when departure_date has passed)
 *   in_transit → arrived     (when arrival_date has passed; ShipmentObserver sets port_operations_until & moves vessel)
 *   arrived    → completed   (when port_operations_until has passed)
 *
 * Vessel status mirrors the active shipment:
 *   scheduled  → "in_port" (docked / loading)
 *   in_transit → "in_transit"
 *   arrived    → "in_port" (unloading)
 *   (no active shipment) → "active"
 */
class ShipmentScheduleAdvancerService
{
    /**
     * @return array{advanced:int,vessels_synced:int}
     */
    public function tick(?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();
        $advanced = 0;

        // scheduled -> in_transit: mark actual_departure_date the first time the shipment runs past its planned time.
        $toDepart = Shipment::query()
            ->whereRaw('LOWER(status) = ?', ['scheduled'])
            ->whereNotNull('departure_date')
            ->where('departure_date', '<=', $now)
            ->get();
        foreach ($toDepart as $shipment) {
            $shipment->forceFill([
                'status' => 'in_transit',
                'actual_departure_date' => $shipment->actual_departure_date ?? $now,
            ])->saveQuietly();
            $advanced++;
        }

        // in_transit -> arrived: setting actual_arrival_date triggers ShipmentObserver which also moves the vessel & containers.
        $toArrive = Shipment::query()
            ->whereRaw('LOWER(status) IN (?,?)', ['in_transit', 'in_progress'])
            ->whereNotNull('arrival_date')
            ->where('arrival_date', '<=', $now)
            ->get();
        foreach ($toArrive as $shipment) {
            $shipment->forceFill([
                'status' => 'arrived',
                'actual_arrival_date' => $shipment->actual_arrival_date ?? $now,
            ])->save();
            $advanced++;
        }

        // arrived -> completed once port operations are done.
        $toComplete = Shipment::query()
            ->whereRaw('LOWER(status) = ?', ['arrived'])
            ->whereNotNull('port_operations_until')
            ->where('port_operations_until', '<=', $now)
            ->get();
        foreach ($toComplete as $shipment) {
            $shipment->forceFill(['status' => 'completed'])->saveQuietly();
            $advanced++;
        }

        $vesselsSynced = $this->syncVesselStatuses($now);

        return ['advanced' => $advanced, 'vessels_synced' => $vesselsSynced];
    }

    /**
     * Make vessel.status reflect the status of its most recent active shipment (docked/in_transit/unloading).
     */
    private function syncVesselStatuses(CarbonImmutable $now): int
    {
        $synced = 0;
        $rows = Shipment::query()
            ->select('vessel_id', 'status', 'departure_date', 'arrival_date', 'port_operations_until', 'updated_at')
            ->whereRaw('LOWER(status) IN (?,?,?,?)', ['scheduled', 'in_transit', 'in_progress', 'arrived'])
            ->orderByDesc('updated_at')
            ->get();

        $bestByVessel = [];
        foreach ($rows as $row) {
            $vid = (int) $row->vessel_id;
            if (! isset($bestByVessel[$vid])) {
                $bestByVessel[$vid] = $row;
            }
        }

        foreach ($bestByVessel as $vid => $row) {
            $desired = match (strtolower((string) $row->status)) {
                'in_transit', 'in_progress' => 'in_transit',
                'arrived' => 'in_port',
                'scheduled' => 'in_port',
                default => 'active',
            };
            $vessel = Vessel::query()->find($vid);
            if ($vessel === null) {
                continue;
            }
            if ((string) $vessel->status !== $desired
                && ($vessel->out_of_service_until === null || $vessel->out_of_service_until->lt($now))) {
                $vessel->forceFill(['status' => $desired])->saveQuietly();
                $synced++;
            }
        }

        return $synced;
    }
}
