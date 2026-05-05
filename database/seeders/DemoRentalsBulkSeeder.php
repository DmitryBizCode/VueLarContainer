<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Many extra demo rentals (past / current / future, mixed statuses) for dashboards and lists.
 * Markers: [demo-scenario:bulk-NNN]. Idempotent: skips indices that already exist.
 */
class DemoRentalsBulkSeeder extends Seeder
{
    public const BULK_COUNT = 56;

    public function run(): void
    {
        $admin = User::query()->where('email', 'romeobackend@gmail.com')->first();
        $clients = [
            User::query()->where('email', 'demo@example.com')->first(),
            User::query()->where('email', 'client2@demo.local')->first(),
            User::query()->where('email', 'client3@demo.local')->first(),
        ];
        if (! $admin || in_array(null, $clients, true)) {
            return;
        }

        /** @var list<array{0: string, 1: string, 2: int, 3: float}> $legs */
        $legs = [
            ['Port of Hamburg', 'Port of Rotterdam', 2, 430.0],
            ['Port of Rotterdam', 'Port of Le Havre', 3, 620.0],
            ['Port of Antwerp', 'Port of Rotterdam', 1, 190.0],
            ['Port of Barcelona', 'Port of Valencia', 1, 380.0],
            ['Port of Valencia', 'Port of Genoa', 3, 1100.0],
            ['Port of Hamburg', 'Port of Bremerhaven', 1, 120.0],
            ['Port of Rotterdam', 'Port of Antwerp', 1, 190.0],
            ['Port of Le Havre', 'Port of Southampton', 2, 320.0],
            ['Port of Gdansk', 'Port of Hamburg', 2, 780.0],
            ['Port of Bilbao', 'Port of Vigo', 1, 520.0],
        ];

        $resolved = [];
        foreach ($legs as $leg) {
            $o = Port::query()->where('name', $leg[0])->value('id');
            $d = Port::query()->where('name', $leg[1])->value('id');
            if (! $o || ! $d) {
                continue;
            }
            $rid = Route::query()
                ->where('origin_port_id', $o)
                ->where('destination_port_id', $d)
                ->where('route_status', 'open')
                ->value('id');
            if (! $rid) {
                continue;
            }
            $resolved[] = [
                'origin_id' => (int) $o,
                'dest_id' => (int) $d,
                'route_id' => (int) $rid,
                'days' => $leg[2],
                'distance' => $leg[3],
            ];
        }

        if ($resolved === []) {
            return;
        }

        $scenarioContainerIds = Rental::query()
            ->where('description', 'not like', '%[demo-scenario:bulk-%')
            ->pluck('container_id')
            ->filter()
            ->unique()
            ->all();

        $containerPool = Container::query()
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->whereNotIn('id', $scenarioContainerIds)
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        if (count($containerPool) < self::BULK_COUNT) {
            return;
        }

        $now = CarbonImmutable::now();
        $cargoCycles = [
            ['electronics'], ['furniture'], ['food'], ['clothing'], ['machinery'], ['other'],
        ];
        $priorities = ['normal', 'normal', 'urgent', 'express'];

        $specs = $this->buildSpecs($now, self::BULK_COUNT);

        foreach ($specs as $idx => $spec) {
            $n = $idx + 1;
            $marker = '[demo-scenario:bulk-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).']';
            if (Rental::query()->where('description', 'like', '%'.$marker.'%')->exists()) {
                continue;
            }

            $leg = $resolved[$idx % count($resolved)];
            $client = $clients[$idx % 3];
            $containerId = (int) $containerPool[$idx];

            $start = $spec['start'];
            $end = $spec['end'];
            $spanDays = max(1, (int) abs($start->diffInDays($end)));
            $price = round(800.0 + $spanDays * 85.0 + $leg['distance'] * 0.35, 2);

            $routeLegs = [[
                'route_id' => $leg['route_id'],
                'origin_port_id' => $leg['origin_id'],
                'destination_port_id' => $leg['dest_id'],
                'estimated_days' => $leg['days'],
                'distance' => $leg['distance'],
            ]];

            $status = $spec['status'];
            $paymentStatus = $spec['payment_status'];
            $reviewedBy = in_array($status, ['pending_approval'], true) ? null : $admin->id;
            $reviewedAt = $reviewedBy ? $start->subDays(min(5, $spanDays)) : null;
            $authorizeCapture = (bool) ($spec['authorize_capture'] ?? false);
            $rejectionReason = $status === 'rejected' ? 'Bulk demo rejection — slot not confirmed '.$marker : null;
            $cancellationReason = $status === 'cancelled' ? 'Customer requested cancellation (demo) '.$marker : null;

            $rental = Rental::query()->create([
                'user_id' => $client->id,
                'container_id' => $containerId,
                'route_id' => $leg['route_id'],
                'origin_port_id' => $leg['origin_id'],
                'destination_port_id' => $leg['dest_id'],
                'start_date' => $start,
                'end_date' => $end,
                'actual_return_date' => $status === 'completed' ? $end : null,
                'rental_days' => $spanDays,
                'cargo_types' => $cargoCycles[$idx % count($cargoCycles)],
                'cargo_details' => 'Bulk seeded rental '.$marker,
                'priority' => $priorities[$idx % count($priorities)],
                'routing_priority' => $idx % 4 === 0 ? 'balanced' : ($idx % 4 === 1 ? 'speed' : 'cost'),
                'loading_type' => 'fcl',
                'delivery_mode' => $idx % 5 === 0 ? 'door_to_port' : 'port_to_port',
                'sustainability_pref' => $idx % 3 === 0 ? 'eco_optimized' : 'standard',
                'insurance_required' => $idx % 2 === 0,
                'requires_customs_clearance' => $idx % 7 === 0,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => $idx % 3 === 0,
                'contact_name' => $client->first_name.' '.$client->last_name,
                'contact_phone' => '+1000555'.str_pad((string) (($idx * 17) % 10000), 4, '0', STR_PAD_LEFT),
                'terms_accepted' => true,
                'estimated_distance' => $leg['distance'],
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => $spanDays,
                    'route_legs' => $routeLegs,
                    'routing_mode' => 'cost',
                ],
                'status' => $status,
                'is_telemetry_active' => in_array($status, ['in_progress', 'active', 'scheduled', 'approved'], true) && $idx % 2 === 0,
                'payment_status' => $paymentStatus,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'payment_approved_at' => $authorizeCapture
                    ? $start->subDays(2)
                    : ($paymentStatus === 'paid' && $status === 'completed' ? $start->addDays(2) : null),
                'payment_approved_by' => $authorizeCapture
                    ? $admin->id
                    : ($paymentStatus === 'paid' && $status === 'completed' ? $admin->id : null),
                'rejection_reason' => $rejectionReason,
                'cancellation_reason' => $cancellationReason,
                'description' => $spec['title'].' '.$marker,
            ]);

            if ($status === 'completed' && $paymentStatus === 'paid') {
                if (Transaction::query()->where('rental_id', $rental->id)->exists()) {
                    continue;
                }
                Transaction::query()->firstOrCreate(
                    [
                        'rental_id' => $rental->id,
                        'external_provider_id' => 'demo-bulk-tx-'.$rental->id,
                    ],
                    [
                        'amount' => $price,
                        'currency' => 'USD',
                        'status' => $idx % 3 === 0 ? 'completed' : 'paid',
                        'transaction_date' => $start->addDays(3),
                        'payment_method' => 'card',
                    ]
                );
            }

            if (in_array($status, ['in_progress', 'active'], true)) {
                $profile = $spec['shipment_profile'] ?? match ($idx % 4) {
                    0 => 'just_started',
                    1 => 'mid_route',
                    2 => 'approaching_destination',
                    default => 'scheduled_leg',
                };
                $this->attachBulkShipment($rental, $leg, $containerId, $now, $profile);
            } elseif ($status === 'completed') {
                Container::query()->whereKey($containerId)->update([
                    'current_port_id' => $leg['dest_id'],
                    'current_status' => 'available',
                ]);
            } elseif (in_array($status, ['rejected', 'cancelled', 'pending_approval'], true)) {
                Container::query()->whereKey($containerId)->update(['current_status' => 'available']);
            }
        }
    }

    /**
     * @return list<array{status: string, payment_status: string, start: CarbonImmutable, end: CarbonImmutable, title: string, authorize_capture?: bool, shipment_profile?: string}>
     */
    private function buildSpecs(CarbonImmutable $now, int $count): array
    {
        $specs = [];
        for ($i = 0; $i < $count; $i++) {
            // 0–9: completed long ago
            if ($i < 10) {
                $end = $now->subDays(380 - $i * 32);
                $start = $end->subDays(16 + ($i % 7));
                $specs[] = [
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Archive completed rental',
                ];

                continue;
            }
            // 10–17: completed recently ended
            if ($i < 18) {
                $j = $i - 10;
                $end = $now->subDays(3 + $j);
                $start = $end->subDays(14 + ($j % 5));
                $specs[] = [
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Just finished rental',
                ];

                continue;
            }
            // 18–27: in progress (underway) — shipment profiles rotate by index
            if ($i < 28) {
                $j = $i - 18;
                $start = $now->subDays(12 + ($j % 6));
                $end = $now->addDays(15 + ($j % 12));
                $profiles = ['just_started', 'mid_route', 'approaching_destination', 'scheduled_leg'];
                $specs[] = [
                    'status' => 'in_progress',
                    'payment_status' => $j % 3 === 0 ? 'pending' : 'paid',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Underway shipment',
                    'shipment_profile' => $profiles[$j % 4],
                ];

                continue;
            }
            // 28–33: in progress (just started)
            if ($i < 34) {
                $j = $i - 28;
                $start = $now->subDays($j % 4);
                $end = $now->addDays(22 + ($j % 9));
                $specs[] = [
                    'status' => 'in_progress',
                    'payment_status' => $j % 2 === 0 ? 'paid' : 'pending',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Rental just started',
                    'shipment_profile' => 'just_started',
                ];

                continue;
            }
            // 34–37: active (telemetry-friendly lifecycle)
            if ($i < 38) {
                $j = $i - 34;
                $start = $now->subDays(9 + $j);
                $end = $now->addDays(12 + ($j % 5));
                $profiles = ['mid_route', 'approaching_destination', 'just_started', 'scheduled_leg'];
                $specs[] = [
                    'status' => 'active',
                    'payment_status' => $j % 2 === 0 ? 'paid' : 'pending',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Active rental (live ops)',
                    'shipment_profile' => $profiles[$j % 4],
                ];

                continue;
            }
            // 38–41: in progress, rental ending in 1–3 days
            if ($i < 42) {
                $j = $i - 38;
                $start = $now->subDays(24 + $j * 2);
                $end = $now->addDays(1 + $j);
                $specs[] = [
                    'status' => 'in_progress',
                    'payment_status' => 'paid',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Rental ending soon',
                    'shipment_profile' => 'approaching_destination',
                ];

                continue;
            }
            // 42–47: scheduled (future)
            if ($i < 48) {
                $j = $i - 42;
                $start = $now->addDays(8 + $j * 5);
                $end = $start->addDays(24 + ($j % 10));
                $specs[] = [
                    'status' => 'scheduled',
                    'payment_status' => $j % 2 === 0 ? 'pending' : 'unpaid',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Future scheduled slot',
                ];

                continue;
            }
            // 48–50: approved upcoming
            if ($i < 51) {
                $j = $i - 48;
                $start = $now->addDays(18 + $j * 7);
                $end = $start->addDays(30);
                $specs[] = [
                    'status' => 'approved',
                    'payment_status' => 'pending',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Approved not yet scheduled',
                    'authorize_capture' => $j > 0,
                ];

                continue;
            }
            // 51–53: pending approval
            if ($i < 54) {
                $j = $i - 51;
                $start = $now->addDays(4 + $j * 6);
                $end = $start->addDays(28);
                $specs[] = [
                    'status' => 'pending_approval',
                    'payment_status' => 'pending',
                    'start' => $start,
                    'end' => $end,
                    'title' => 'Queue pending approval',
                ];

                continue;
            }
            // 54–55: rejected / cancelled
            $j = $i - 54;
            $start = $now->subDays(20 + $j * 3);
            $end = $start->addDays(25);
            $specs[] = [
                'status' => $j === 0 ? 'rejected' : 'cancelled',
                'payment_status' => $j === 0 ? 'rejected_by_approval' : 'cancelled',
                'start' => $start,
                'end' => $end,
                'title' => $j === 0 ? 'Rejected bulk request' : 'Cancelled bulk booking',
            ];
        }

        return $specs;
    }

    /**
     * @param  array{origin_id: int, dest_id: int, route_id: int, days: int, distance: float}  $leg
     */
    private function attachBulkShipment(
        Rental $rental,
        array $leg,
        int $containerId,
        CarbonImmutable $now,
        string $profile,
    ): void {
        Container::query()->whereKey($containerId)->update(['current_status' => 'in_use']);
        $vesselId = Vessel::query()
            ->where('current_port_id', $leg['origin_id'])
            ->orderBy('id')
            ->value('id');
        if (! $vesselId) {
            return;
        }

        $routeDays = max(1, $leg['days']);

        switch ($profile) {
            case 'just_started':
                $dep = $now->subDay();
                $arrival = $now->addDays(max(5, $routeDays + 4));
                $status = 'in_transit';
                $actualDep = $dep;
                $actualArr = null;
                $portOps = null;

                break;
            case 'mid_route':
                $dep = $now->subDays(6);
                $arrival = $now->addDays(5);
                $status = 'in_transit';
                $actualDep = $dep;
                $actualArr = null;
                $portOps = null;

                break;
            case 'approaching_destination':
                $dep = $now->subDays(10);
                $arrival = $now->addDays(2);
                $status = 'in_transit';
                $actualDep = $dep;
                $actualArr = null;
                $portOps = null;

                break;
            case 'scheduled_leg':
                $dep = $now->addDays(2);
                $arrival = $dep->addDays($routeDays);
                $status = 'scheduled';
                $actualDep = null;
                $actualArr = null;
                $portOps = null;

                break;
            default:
                $dep = $now->subDays(3);
                $arrival = $dep->addDays($routeDays);
                $status = 'in_transit';
                $actualDep = $dep;
                $actualArr = null;
                $portOps = null;
        }

        $shipment = Shipment::query()->create([
            'vessel_id' => $vesselId,
            'route_id' => $leg['route_id'],
            'leg_sequence' => 1,
            'departure_date' => $dep,
            'arrival_date' => $arrival,
            'actual_departure_date' => $actualDep,
            'actual_arrival_date' => $actualArr,
            'port_operations_until' => $portOps,
            'tracking_number' => $this->uniqueTracking(),
            'status' => $status,
        ]);
        ShipmentItem::query()->create([
            'shipment_id' => $shipment->id,
            'container_id' => $containerId,
            'rental_id' => $rental->id,
            'loaded_at' => $actualDep ?? $dep,
            'condition_on_arrival' => 'good',
            'notes' => 'DemoRentalsBulkSeeder:'.$profile,
        ]);
    }

    private function uniqueTracking(): string
    {
        do {
            $t = 'BLK-'.strtoupper(Str::random(10));
        } while (Shipment::query()->where('tracking_number', $t)->exists());

        return $t;
    }
}
