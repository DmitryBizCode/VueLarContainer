<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Container;
use App\Models\Incident;
use App\Models\Inquiry;
use App\Models\Metric;
use App\Models\Notification;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserMessage;
use App\Models\Vessel;
use App\Services\Metrics\MetricsPartitionManager;
use App\Services\RentalShipmentProvisionerService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Linked demo data for dashboards, admin, finance, inquiries, messaging. Idempotent via [demo-scenario:...] markers.
 */
class DemoBusinessScenarioSeeder extends Seeder
{
    /** @var list<int> */
    private array $usedContainerIds = [];

    public function run(): void
    {
        $this->seedRentalsShipmentsTransactions();
        $this->seedInquiries();
        $this->seedNotificationsAndMessages();
    }

    private function portId(string $name): ?int
    {
        return Port::query()->where('name', $name)->value('id');
    }

    private function routeOpenId(int $originPortId, int $destPortId): ?int
    {
        return Route::query()
            ->where('origin_port_id', $originPortId)
            ->where('destination_port_id', $destPortId)
            ->where('route_status', 'open')
            ->value('id');
    }

    private function pickContainerAtPort(int $portId): ?Container
    {
        $q = Container::query()
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->where('current_port_id', $portId)
            ->whereNotIn('id', $this->usedContainerIds)
            ->orderBy('id');

        $c = $q->first();
        if ($c) {
            $this->usedContainerIds[] = (int) $c->id;
        }

        return $c;
    }

    private function uniqueTracking(): string
    {
        do {
            $t = 'DEMO-'.strtoupper(Str::random(10));
        } while (Shipment::query()->where('tracking_number', $t)->exists());

        return $t;
    }

    private function logRental(
        int $userId,
        string $action,
        int $rentalId,
        ?array $oldValues,
        ?array $newValues,
        string $description,
        CarbonImmutable $at,
    ): void {
        ActivityLog::query()->create([
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DemoBusinessScenarioSeeder',
            'action' => $action,
            'model_name' => 'Rental',
            'model_id' => $rentalId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'request_path' => '/admin/seed-demo',
            'created_at' => $at,
        ]);
    }

    private function seedRentalsShipmentsTransactions(): void
    {
        $hamburgId = $this->portId('Port of Hamburg');
        $rotterdamId = $this->portId('Port of Rotterdam');
        $leHavreId = $this->portId('Port of Le Havre');
        $bremerhavenId = $this->portId('Port of Bremerhaven');
        $antwerpId = $this->portId('Port of Antwerp');

        if (! $hamburgId || ! $rotterdamId || ! $leHavreId || ! $bremerhavenId || ! $antwerpId) {
            return;
        }

        $routeHamburgRotterdam = $this->routeOpenId($hamburgId, $rotterdamId);
        $routeRotterdamLeHavre = $this->routeOpenId($rotterdamId, $leHavreId);
        $routeHamburgBremerhaven = $this->routeOpenId($hamburgId, $bremerhavenId);
        $routeAntwerpRotterdam = $this->routeOpenId($antwerpId, $rotterdamId);

        if (! $routeHamburgRotterdam || ! $routeRotterdamLeHavre || ! $routeHamburgBremerhaven || ! $routeAntwerpRotterdam) {
            return;
        }

        $admin1 = User::query()->where('email', 'romeobackend@gmail.com')->first();
        $admin2 = User::query()->where('email', 'admin2@demo.local')->first();
        $demoClient = User::query()->where('email', 'demo@example.com')->first();
        $client2 = User::query()->where('email', 'client2@demo.local')->first();
        $client3 = User::query()->where('email', 'client3@demo.local')->first();

        if (! $admin1 || ! $demoClient || ! $client2 || ! $client3) {
            return;
        }

        $adminReviewer2 = $admin2 ?? $admin1;

        $now = CarbonImmutable::now();
        $routeLegsHr = [
            [
                'route_id' => (int) $routeHamburgRotterdam,
                'origin_port_id' => $hamburgId,
                'destination_port_id' => $rotterdamId,
                'estimated_days' => 2,
                'distance' => 430.0,
            ],
        ];

        // --- Completed rental (demo client): Hamburg → Rotterdam, past, paid, container now in Rotterdam ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-completed]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            if ($container) {
                $start = $now->subDays(75);
                $end = $now->subDays(40);
                $price = 2650.00;
                $rental = Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeHamburgRotterdam,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $rotterdamId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'actual_return_date' => $end,
                    'rental_days' => 35,
                    'cargo_types' => ['furniture'],
                    'cargo_details' => 'Office fixtures — completed demo cycle [demo-scenario:rental-completed]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => true,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Demo Client',
                    'contact_phone' => '+10005550100',
                    'terms_accepted' => true,
                    'estimated_distance' => 430.0,
                    'price' => $price,
                    'price_breakdown' => ['estimated_total' => $price, 'days' => 35, 'route_legs' => $routeLegsHr, 'routing_mode' => 'cost'],
                    'status' => 'completed',
                    'is_telemetry_active' => false,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'payment_approved_at' => $start,
                    'payment_approved_by' => $adminReviewer2->id,
                    'description' => 'Closed rental Hamburg → Rotterdam (paid, historical) [demo-scenario:rental-completed]',
                ]);

                if (! Transaction::query()->where('rental_id', $rental->id)->exists()) {
                    Transaction::query()->firstOrCreate(
                        [
                            'rental_id' => $rental->id,
                            'external_provider_id' => 'demo-seed-tx-completed-'.$rental->id,
                        ],
                        [
                            'amount' => $price,
                            'currency' => 'USD',
                            'status' => 'paid',
                            'transaction_date' => $start->addDays(2),
                            'payment_method' => 'card',
                        ]
                    );
                }

                $t1 = $start->subDays(2);
                $this->logRental($admin1->id, 'status_changed_to_approved', $rental->id, [
                    'status' => 'pending_approval',
                    'payment_status' => 'pending',
                ], [
                    'status' => 'approved',
                    'payment_status' => 'pending',
                    'reviewed_by' => $admin1->id,
                ], 'Rental #'.$rental->id.' approved by '.$admin1->first_name.' '.$admin1->last_name.' [demo]', $t1);

                $t2 = $start->addDay();
                $this->logRental($admin1->id, 'status_changed_to_in_progress', $rental->id, [
                    'status' => 'approved',
                ], [
                    'status' => 'in_progress',
                ], 'Rental #'.$rental->id.' marked in progress', $t2);

                $t3 = $end->subDay();
                $this->logRental($admin1->id, 'status_changed_to_completed', $rental->id, [
                    'status' => 'in_progress',
                ], [
                    'status' => 'completed',
                ], 'Rental #'.$rental->id.' completed — container released in Rotterdam', $t3);

                $container->update([
                    'current_port_id' => $rotterdamId,
                    'current_status' => 'available',
                ]);
            }
        }

        // --- In progress + shipment + incident + metrics (demo client) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-active]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            $vesselId = Vessel::query()->where('current_port_id', $hamburgId)->orderBy('id')->value('id');
            if ($container && $routeHamburgRotterdam && $vesselId) {
                $container->update(['current_status' => 'in_use']);

                $rental = Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeHamburgRotterdam,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $rotterdamId,
                    'start_date' => $now->subDays(4),
                    'end_date' => $now->addDays(26),
                    'rental_days' => 30,
                    'cargo_types' => ['electronics'],
                    'cargo_details' => 'Active leg demo cargo [demo-scenario:rental-active]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => false,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => false,
                    'contact_name' => 'Demo Client',
                    'contact_phone' => '+10005550199',
                    'terms_accepted' => true,
                    'estimated_distance' => 430.0,
                    'price' => 2850.5,
                    'price_breakdown' => [
                        'estimated_total' => 2850.5,
                        'days' => 30,
                        'route_legs' => $routeLegsHr,
                        'routing_mode' => 'time',
                    ],
                    'status' => 'in_progress',
                    'is_telemetry_active' => true,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $now->subDays(5),
                    'description' => 'Active leg Hamburg → Rotterdam [demo-scenario:rental-active]',
                ]);

                $dep = $now->subDays(2);
                $shipment = Shipment::query()->create([
                    'vessel_id' => $vesselId,
                    'route_id' => $routeHamburgRotterdam,
                    'leg_sequence' => 1,
                    'departure_date' => $dep,
                    'arrival_date' => $dep->addDays(2),
                    'actual_departure_date' => $dep,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'in_transit',
                ]);

                ShipmentItem::query()->create([
                    'shipment_id' => $shipment->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'DemoBusinessScenarioSeeder active shipment',
                ]);

                Incident::query()->firstOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'description' => 'Minor reefer alarm spike — engineering notified [demo-scenario:incident-active]',
                    ],
                    [
                        'type' => 'equipment',
                        'severity' => 'low',
                        'container_id' => $container->id,
                        'reported_at' => $dep->addHours(6),
                        'resolved_at' => null,
                        'resolution_status' => 'under_investigation',
                    ]
                );

                app(MetricsPartitionManager::class)->ensurePartitionForRentalId((int) $rental->id);

                foreach ([
                    ['temperature_c', 4.2, '°C', $now->subHours(3)],
                    ['humidity_pct', 78.0, '%', $now->subHours(2)],
                    ['co2_ppm', 620.0, 'ppm', $now->subHour()],
                ] as $row) {
                    Metric::query()->create([
                        'container_id' => $container->id,
                        'rental_id' => $rental->id,
                        'type' => $row[0],
                        'value' => $row[1],
                        'unit' => $row[2],
                        'recorded_at' => $row[3],
                    ]);
                }

                $this->logRental($admin1->id, 'status_changed_to_approved', $rental->id, null, ['status' => 'approved'], 'Seeded approval for active rental #'.$rental->id, $now->subDays(6));
            }
        }

        // --- Pending approval (client2): Rotterdam → Le Havre ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-pending]%')->exists()) {
            $container = $this->pickContainerAtPort($rotterdamId);
            if ($container && $routeRotterdamLeHavre) {
                Rental::query()->create([
                    'user_id' => $client2->id,
                    'container_id' => $container->id,
                    'route_id' => $routeRotterdamLeHavre,
                    'origin_port_id' => $rotterdamId,
                    'destination_port_id' => $leHavreId,
                    'start_date' => $now->addDays(7),
                    'end_date' => $now->addDays(38),
                    'rental_days' => 31,
                    'cargo_types' => ['machinery'],
                    'cargo_details' => null,
                    'priority' => 'urgent',
                    'routing_priority' => 'speed',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => true,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Elena Vogel',
                    'contact_phone' => '+49301234567',
                    'terms_accepted' => true,
                    'estimated_distance' => 620.0,
                    'price' => 4100.0,
                    'price_breakdown' => [
                        'estimated_total' => 4100.0,
                        'days' => 31,
                        'route_legs' => [[
                            'route_id' => (int) $routeRotterdamLeHavre,
                            'origin_port_id' => $rotterdamId,
                            'destination_port_id' => $leHavreId,
                            'estimated_days' => 3,
                            'distance' => 620.0,
                        ]],
                        'routing_mode' => 'time',
                    ],
                    'status' => 'pending_approval',
                    'is_telemetry_active' => true,
                    'payment_status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'description' => 'Awaiting ops approval Rotterdam → Le Havre [demo-scenario:rental-pending]',
                ]);
            }
        }

        // --- Rejected (client3): Hamburg → Bremerhaven ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-rejected]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            if ($container && $routeHamburgBremerhaven) {
                $reviewedAt = $now->subDays(3);
                $rental = Rental::query()->create([
                    'user_id' => $client3->id,
                    'container_id' => $container->id,
                    'route_id' => $routeHamburgBremerhaven,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $bremerhavenId,
                    'start_date' => $now->addDays(5),
                    'end_date' => $now->addDays(12),
                    'rental_days' => 7,
                    'cargo_types' => ['other'],
                    'cargo_details' => 'Short coastal hop [demo-scenario:rental-rejected]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => false,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => false,
                    'contact_name' => 'Marcus Okonkwo',
                    'contact_phone' => '+442079460001',
                    'terms_accepted' => true,
                    'estimated_distance' => 120.0,
                    'price' => 890.0,
                    'price_breakdown' => ['estimated_total' => 890.0, 'days' => 7, 'route_legs' => [], 'routing_mode' => 'cost'],
                    'status' => 'rejected',
                    'is_telemetry_active' => false,
                    'payment_status' => 'rejected_by_approval',
                    'reviewed_by' => $adminReviewer2->id,
                    'reviewed_at' => $reviewedAt,
                    'rejection_reason' => 'Capacity constrained on feeder schedule — please resubmit for next week [demo-scenario:rental-rejected]',
                    'description' => 'Rejected request Hamburg → Bremerhaven [demo-scenario:rental-rejected]',
                ]);

                $this->logRental(
                    $adminReviewer2->id,
                    'status_changed_to_rejected',
                    $rental->id,
                    ['status' => 'pending_approval'],
                    ['status' => 'rejected', 'payment_status' => 'rejected_by_approval'],
                    'Rental #'.$rental->id.' rejected by '.$adminReviewer2->first_name.' '.$adminReviewer2->last_name,
                    $reviewedAt
                );

                $rental->forceFill(['updated_at' => $now->subDays(12)])->saveQuietly();
            }
        }

        // --- Scheduled future (client2): Antwerp → Rotterdam with shipment ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-scheduled]%')->exists()) {
            $container = $this->pickContainerAtPort($antwerpId);
            if ($container && $routeAntwerpRotterdam) {
                $start = $now->addDays(12);
                $end = $now->addDays(45);
                $rental = Rental::query()->create([
                    'user_id' => $client2->id,
                    'container_id' => $container->id,
                    'route_id' => $routeAntwerpRotterdam,
                    'origin_port_id' => $antwerpId,
                    'destination_port_id' => $rotterdamId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 33,
                    'cargo_types' => ['clothing'],
                    'cargo_details' => 'Seasonal stock — future departure [demo-scenario:rental-scheduled]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'eco_optimized',
                    'insurance_required' => false,
                    'requires_customs_clearance' => true,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Elena Vogel',
                    'contact_phone' => '+49301234567',
                    'terms_accepted' => true,
                    'estimated_distance' => 190.0,
                    'price' => 1950.0,
                    'price_breakdown' => [
                        'estimated_total' => 1950.0,
                        'days' => 33,
                        'route_legs' => [[
                            'route_id' => (int) $routeAntwerpRotterdam,
                            'origin_port_id' => $antwerpId,
                            'destination_port_id' => $rotterdamId,
                            'estimated_days' => 1,
                            'distance' => 190.0,
                        ]],
                        'routing_mode' => 'balanced',
                    ],
                    'status' => 'approved',
                    'is_telemetry_active' => true,
                    'payment_status' => 'pending',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $now->subDay(),
                    'description' => 'Approved future leg Antwerp → Rotterdam [demo-scenario:rental-scheduled]',
                ]);

                try {
                    $rental->refresh();
                    app(RentalShipmentProvisionerService::class)->provisionForApprovedRental($rental);
                } catch (\Throwable $e) {
                    report($e);
                    $vesselId = Vessel::query()->where('current_port_id', $antwerpId)->orderBy('id')->value('id');
                    if ($vesselId && ! ShipmentItem::query()->where('rental_id', $rental->id)->exists()) {
                        $departure = $start;
                        $arrival = $start->addDays(2);
                        $shipment = Shipment::query()->create([
                            'vessel_id' => $vesselId,
                            'route_id' => $routeAntwerpRotterdam,
                            'leg_sequence' => 1,
                            'departure_date' => $departure,
                            'arrival_date' => $arrival,
                            'actual_departure_date' => null,
                            'actual_arrival_date' => null,
                            'port_operations_until' => null,
                            'tracking_number' => $this->uniqueTracking(),
                            'status' => 'scheduled',
                        ]);
                        ShipmentItem::query()->create([
                            'shipment_id' => $shipment->id,
                            'container_id' => $container->id,
                            'rental_id' => $rental->id,
                            'loaded_at' => null,
                            'condition_on_arrival' => 'good',
                            'notes' => 'Fallback shipment from DemoBusinessScenarioSeeder',
                        ]);
                    }
                }

                $rental->update(['status' => 'scheduled']);
                $this->logRental($admin1->id, 'status_changed_to_scheduled', $rental->id, ['status' => 'approved'], ['status' => 'scheduled'], 'Rental #'.$rental->id.' scheduled for '.$start->toDateString(), $now);
            }
        }

        // --- Approved + payment auth: awaiting capture (no pending PSP tx) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:rental-awaiting-capture]%')->exists()) {
            $container = $this->pickContainerAtPort($leHavreId);
            if ($container && $routeRotterdamLeHavre) {
                $start = $now->addDays(20);
                $end = $now->addDays(52);
                $authAt = $now->subDays(3);
                Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeRotterdamLeHavre,
                    'origin_port_id' => $rotterdamId,
                    'destination_port_id' => $leHavreId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 32,
                    'cargo_types' => ['food'],
                    'cargo_details' => 'Capture authorized; settlement pending [demo-scenario:rental-awaiting-capture]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => true,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Demo Client',
                    'contact_phone' => '+10005550100',
                    'terms_accepted' => true,
                    'estimated_distance' => 620.0,
                    'price' => 3250.0,
                    'price_breakdown' => [
                        'estimated_total' => 3250.0,
                        'days' => 32,
                        'route_legs' => [[
                            'route_id' => (int) $routeRotterdamLeHavre,
                            'origin_port_id' => $rotterdamId,
                            'destination_port_id' => $leHavreId,
                            'estimated_days' => 3,
                            'distance' => 620.0,
                        ]],
                        'routing_mode' => 'balanced',
                    ],
                    'status' => 'approved',
                    'is_telemetry_active' => false,
                    'payment_status' => 'pending',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $now->subDays(6),
                    'payment_approved_at' => $authAt,
                    'payment_approved_by' => $adminReviewer2->id,
                    'description' => 'Approved; capture authorized, awaiting settlement [demo-scenario:rental-awaiting-capture]',
                ]);
            }
        }

        $this->seedDemoFailedTransactionsForCharts($now);
    }

    private function seedDemoFailedTransactionsForCharts(CarbonImmutable $now): void
    {
        $rentalIds = Rental::query()
            ->where('description', 'like', '%demo-scenario%')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.rental_id', 'rentals.id');
            })
            ->orderBy('id')
            ->limit(20)
            ->pluck('id');

        if ($rentalIds->isEmpty()) {
            return;
        }

        $slots = [
            [$now->startOfMonth()->subMonths(1)->addDays(4), 0],
            [$now->startOfMonth()->subMonths(4)->addDays(6), 1],
            [$now->startOfMonth()->subMonths(8)->addDays(2), 2],
        ];

        foreach ($slots as [$txDate, $offset]) {
            $rentalId = $rentalIds->get($offset);
            if (! $rentalId) {
                continue;
            }

            if (! Transaction::query()->where('rental_id', $rentalId)->exists()) {
                Transaction::query()->firstOrCreate(
                    [
                        'rental_id' => $rentalId,
                        'external_provider_id' => 'demo-chart-failed-'.$txDate->format('Y-m-d'),
                    ],
                    [
                        'amount' => 455.0 + $offset * 55,
                        'currency' => 'USD',
                        'status' => 'failed',
                        'transaction_date' => $txDate,
                        'payment_method' => 'card',
                        'status_note' => 'Demo: issuer declined (seed)',
                    ]
                );
            }
        }
    }

    private function seedInquiries(): void
    {
        $client2 = User::query()->where('email', 'client2@demo.local')->value('id');
        $demo = User::query()->where('email', 'demo@example.com')->value('id');

        $rows = [
            [
                'key' => '[demo-scenario:inquiry-new]',
                'name' => 'Guest User',
                'email' => 'prospect@example.com',
                'subject' => 'Question about reefer rates [demo-scenario:inquiry-new]',
                'message' => 'We need two 40ft reefers Hamburg–Valencia next month. Could you share indicative pricing?',
                'handling_status' => Inquiry::HANDLING_NEW,
                'submitted_by_user_id' => null,
                'admin_notes' => null,
            ],
            [
                'key' => '[demo-scenario:inquiry-progress]',
                'name' => 'Elena Vogel',
                'email' => 'client2@demo.local',
                'subject' => 'Follow-up on Le Havre booking [demo-scenario:inquiry-progress]',
                'message' => 'Please confirm cut-off times for our pending Rotterdam → Le Havre request.',
                'handling_status' => Inquiry::HANDLING_IN_PROGRESS,
                'submitted_by_user_id' => $client2,
                'admin_notes' => 'Assigned to ops; called client 10:00 CET [demo-scenario:inquiry-progress]',
            ],
            [
                'key' => '[demo-scenario:inquiry-rejected]',
                'name' => 'Spam Bot',
                'email' => 'seo-winner@invalid.test',
                'subject' => 'SEO services cheap [demo-scenario:inquiry-rejected]',
                'message' => 'Guaranteed page one ranking...',
                'handling_status' => Inquiry::HANDLING_REJECTED,
                'submitted_by_user_id' => null,
                'admin_notes' => 'Commercial spam — no maritime relevance [demo-scenario:inquiry-rejected]',
            ],
            [
                'key' => '[demo-scenario:inquiry-closed]',
                'name' => 'Demo Client',
                'email' => 'demo@example.com',
                'subject' => 'Invoice copy request [demo-scenario:inquiry-closed]',
                'message' => 'Please resend the PDF for rental paid last month.',
                'handling_status' => Inquiry::HANDLING_CLOSED,
                'submitted_by_user_id' => $demo,
                'admin_notes' => 'Sent duplicate invoice; case closed [demo-scenario:inquiry-closed]',
            ],
        ];

        foreach ($rows as $row) {
            $key = $row['key'];
            if (Inquiry::query()->where('subject', 'like', '%'.$key.'%')->exists()) {
                continue;
            }
            Inquiry::query()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone_number' => null,
                'telegram_username' => null,
                'subject' => $row['subject'],
                'message' => $row['message'],
                'source' => 'website',
                'handling_status' => $row['handling_status'],
                'admin_notes' => $row['admin_notes'],
                'converted_user_id' => null,
                'submitted_by_user_id' => $row['submitted_by_user_id'],
            ]);
        }
    }

    private function seedNotificationsAndMessages(): void
    {
        $demo = User::query()->where('email', 'demo@example.com')->first();
        $client2 = User::query()->where('email', 'client2@demo.local')->first();
        $admin1 = User::query()->where('email', 'romeobackend@gmail.com')->first();

        if (! $demo || ! $client2 || ! $admin1) {
            return;
        }

        $titles = [
            ['user_id' => $demo->id, 'title' => 'Rental update [demo-scenario:notify]', 'read' => false],
            ['user_id' => $demo->id, 'title' => 'Payment received [demo-scenario:notify]', 'read' => true],
            ['user_id' => $client2->id, 'title' => 'Action required: approval pending [demo-scenario:notify]', 'read' => false],
        ];

        foreach ($titles as $t) {
            Notification::query()->firstOrCreate(
                [
                    'user_id' => $t['user_id'],
                    'title' => $t['title'],
                ],
                [
                    'message' => 'Demo notification for dashboard / bell. Open rentals center to review linked activity.',
                    'type' => 'info',
                    'action_url' => route('rentals.center'),
                    'is_read' => $t['read'],
                ]
            );
        }

        UserMessage::query()->firstOrCreate(
            [
                'recipient_user_id' => $demo->id,
                'subject' => 'Welcome to operations messaging [demo-scenario:message]',
            ],
            [
                'sender_user_id' => $admin1->id,
                'body' => 'Hello — this is a seeded message from '.$admin1->first_name.' regarding your active Hamburg shipment. Reply is not required for the demo.',
                'read_at' => now()->subHour(),
            ]
        );

        UserMessage::query()->firstOrCreate(
            [
                'recipient_user_id' => $admin1->id,
                'subject' => 'Client question (demo) [demo-scenario:message]',
            ],
            [
                'sender_user_id' => $demo->id,
                'body' => 'Demo client asking if we can extend the rental end date by two days.',
                'read_at' => null,
            ]
        );
    }
}
