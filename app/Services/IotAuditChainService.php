<?php

namespace App\Services;

use App\Models\IotAuditChain;
use Illuminate\Support\Facades\DB;

class IotAuditChainService
{
    public const EVENT_ACTUATOR_UPDATED = 'actuator_updated';

    public const EVENT_SENSOR_TOGGLED = 'sensor_toggled';

    public const EVENT_SIMULATION_TICK = 'simulation_tick';

    public function append(
        int $containerId,
        string $eventType,
        array $payload,
        ?int $rentalId = null,
        ?int $userId = null
    ): IotAuditChain {
        return DB::transaction(function () use ($containerId, $eventType, $payload, $rentalId, $userId) {
            $prev = IotAuditChain::query()
                ->where('container_id', $containerId)
                ->orderByDesc('sequence')
                ->first();

            $sequence = $prev ? $prev->sequence + 1 : 1;
            $prevHash = $prev?->row_hash;
            $now = now();
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $rowHash = hash('sha256', ($prevHash ?? '').'|'.$sequence.'|'.$payloadJson.'|'.$now->toIso8601String());

            return IotAuditChain::query()->create([
                'container_id' => $containerId,
                'rental_id' => $rentalId,
                'user_id' => $userId,
                'event_type' => $eventType,
                'payload' => $payload,
                'sequence' => $sequence,
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
            ]);
        });
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection<int, IotAuditChain>
     */
    public function forRental(int $rentalId, int $containerId, int $limit = 50, ?int $beforeId = null)
    {
        $query = IotAuditChain::query()
            ->where('container_id', $containerId)
            ->where('rental_id', $rentalId)
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('sequence');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit)->get();
    }
}
