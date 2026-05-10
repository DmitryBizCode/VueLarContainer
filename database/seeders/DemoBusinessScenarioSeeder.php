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
        $this->seedRomeoJourneyScenarios();
        $this->seedDemoClientMapPackRentals();
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
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $reviewedAt,
                    'rejection_reason' => 'Capacity constrained on feeder schedule — please resubmit for next week [demo-scenario:rental-rejected]',
                    'description' => 'Rejected request Hamburg → Bremerhaven [demo-scenario:rental-rejected]',
                ]);

                $this->logRental(
                    $admin1->id,
                    'status_changed_to_rejected',
                    $rental->id,
                    ['status' => 'pending_approval'],
                    ['status' => 'rejected', 'payment_status' => 'rejected_by_approval'],
                    'Rental #'.$rental->id.' rejected by '.$admin1->first_name.' '.$admin1->last_name,
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
                            'loaded_at' => $departure,
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

    /**
     * Extra journey matrix: multi-leg transshipment, hub ops, near-final, ending soon, active IoT, finance — approvals as romeobackend@gmail.com.
     */
    private function seedRomeoJourneyScenarios(): void
    {
        $hamburgId = $this->portId('Port of Hamburg');
        $rotterdamId = $this->portId('Port of Rotterdam');
        $leHavreId = $this->portId('Port of Le Havre');
        $antwerpId = $this->portId('Port of Antwerp');

        if (! $hamburgId || ! $rotterdamId || ! $leHavreId || ! $antwerpId) {
            return;
        }

        $routeHamburgRotterdam = $this->routeOpenId($hamburgId, $rotterdamId);
        $routeRotterdamLeHavre = $this->routeOpenId($rotterdamId, $leHavreId);
        $routeAntwerpRotterdam = $this->routeOpenId($antwerpId, $rotterdamId);

        if (! $routeHamburgRotterdam || ! $routeRotterdamLeHavre || ! $routeAntwerpRotterdam) {
            return;
        }

        $admin1 = User::query()->where('email', 'romeobackend@gmail.com')->first();
        $demoClient = User::query()->where('email', 'demo@example.com')->first();
        $client2 = User::query()->where('email', 'client2@demo.local')->first();

        if (! $admin1 || ! $demoClient || ! $client2) {
            return;
        }

        $now = CarbonImmutable::now();
        $vesselAt = static fn (int $portId): ?int => Vessel::query()
            ->where('current_port_id', $portId)
            ->orderBy('id')
            ->value('id');

        $twoLegBreakdown = function (float $price, int $days) use ($routeHamburgRotterdam, $routeRotterdamLeHavre, $hamburgId, $rotterdamId, $leHavreId): array {
            return [
                'estimated_total' => $price,
                'days' => $days,
                'route_legs' => [
                    [
                        'route_id' => (int) $routeHamburgRotterdam,
                        'origin_port_id' => $hamburgId,
                        'destination_port_id' => $rotterdamId,
                        'estimated_days' => 2,
                        'distance' => 430.0,
                    ],
                    [
                        'route_id' => (int) $routeRotterdamLeHavre,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $leHavreId,
                        'estimated_days' => 3,
                        'distance' => 620.0,
                    ],
                ],
                'routing_mode' => 'balanced',
            ];
        };

        // --- Transshipment: leg 1 completed, leg 2 in transit (demo client) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-tranship-2leg]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            $v1 = $vesselAt($hamburgId);
            $v2 = $vesselAt($rotterdamId);
            if ($container && $v1 && $v2) {
                $container->update(['current_status' => 'in_use', 'current_port_id' => $rotterdamId]);
                $start = $now->subDays(18);
                $end = $now->addDays(20);
                $price = 5120.0;
                $rental = Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeRotterdamLeHavre,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $leHavreId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 38,
                    'cargo_types' => ['machinery'],
                    'cargo_details' => 'Transship via Rotterdam [demo-scenario:romeo-tranship-2leg]',
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
                    'contact_phone' => '+10005550201',
                    'terms_accepted' => true,
                    'estimated_distance' => 1050.0,
                    'price' => $price,
                    'price_breakdown' => $twoLegBreakdown($price, 38),
                    'status' => 'in_progress',
                    'is_telemetry_active' => true,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'description' => 'Hamburg → Le Havre via Rotterdam (leg 2 underway) [demo-scenario:romeo-tranship-2leg]',
                ]);

                $leg1Dep = $now->subDays(14);
                $leg1Arr = $now->subDays(11);
                $ship1 = Shipment::query()->create([
                    'vessel_id' => $v1,
                    'route_id' => $routeHamburgRotterdam,
                    'leg_sequence' => 1,
                    'departure_date' => $leg1Dep,
                    'arrival_date' => $leg1Arr,
                    'actual_departure_date' => $leg1Dep,
                    'actual_arrival_date' => $leg1Arr,
                    'port_operations_until' => $leg1Arr->addDay(),
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'completed',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship1->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $leg1Dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo seed leg 1 complete',
                ]);

                $leg2Dep = $now->subDays(2);
                $leg2Arr = $now->addDays(4);
                $ship2 = Shipment::query()->create([
                    'vessel_id' => $v2,
                    'route_id' => $routeRotterdamLeHavre,
                    'leg_sequence' => 2,
                    'departure_date' => $leg2Dep,
                    'arrival_date' => $leg2Arr,
                    'actual_departure_date' => $leg2Dep,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'in_transit',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship2->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $leg2Dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo seed leg 2 underway',
                ]);

                $this->logRental($admin1->id, 'romeo_tranship_seeded', $rental->id, null, null, 'Seeded transshipment demo (2 legs) [demo-scenario:romeo-tranship-2leg]', $now);
            }
        }

        // --- Hub: leg 1 arrived, port ops ongoing; leg 2 scheduled (client2) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-hub-ops]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            $v1 = $vesselAt($hamburgId);
            $v2 = $vesselAt($rotterdamId);
            if ($container && $v1 && $v2) {
                $container->update(['current_status' => 'in_use', 'current_port_id' => $rotterdamId]);
                $start = $now->subDays(10);
                $end = $now->addDays(28);
                $price = 4980.0;
                $rental = Rental::query()->create([
                    'user_id' => $client2->id,
                    'container_id' => $container->id,
                    'route_id' => $routeRotterdamLeHavre,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $leHavreId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 38,
                    'cargo_types' => ['clothing'],
                    'cargo_details' => 'Hub dwell + onward booking [demo-scenario:romeo-hub-ops]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => false,
                    'requires_customs_clearance' => true,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Elena Vogel',
                    'contact_phone' => '+49301234567',
                    'terms_accepted' => true,
                    'estimated_distance' => 1050.0,
                    'price' => $price,
                    'price_breakdown' => $twoLegBreakdown($price, 38),
                    'status' => 'in_progress',
                    'is_telemetry_active' => true,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'description' => 'Rotterdam hub — ops window then feeder [demo-scenario:romeo-hub-ops]',
                ]);

                $hubArr = $now->subDay();
                $ship1 = Shipment::query()->create([
                    'vessel_id' => $v1,
                    'route_id' => $routeHamburgRotterdam,
                    'leg_sequence' => 1,
                    'departure_date' => $now->subDays(8),
                    'arrival_date' => $hubArr,
                    'actual_departure_date' => $now->subDays(8),
                    'actual_arrival_date' => $hubArr,
                    'port_operations_until' => $now->addDays(3),
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'arrived',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship1->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $now->subDays(8),
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo hub leg 1 arrived',
                ]);

                $leg2Departure = $now->addDays(4);
                $leg2Arrival = $now->addDays(9);
                $ship2 = Shipment::query()->create([
                    'vessel_id' => $v2,
                    'route_id' => $routeRotterdamLeHavre,
                    'leg_sequence' => 2,
                    'departure_date' => $leg2Departure,
                    'arrival_date' => $leg2Arrival,
                    'actual_departure_date' => null,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'scheduled',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship2->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $leg2Departure,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo hub leg 2 scheduled',
                ]);

                $this->logRental($admin1->id, 'romeo_hub_seeded', $rental->id, null, null, 'Seeded hub port ops demo [demo-scenario:romeo-hub-ops]', $now);
            }
        }

        // --- Near final: single leg, arrival in ~36h (demo client) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-near-final]%')->exists()) {
            $container = $this->pickContainerAtPort($antwerpId);
            $v = $vesselAt($antwerpId);
            if ($container && $v && $routeAntwerpRotterdam) {
                $container->update(['current_status' => 'in_use']);
                $start = $now->subDays(6);
                $end = $now->addDays(18);
                $price = 2100.0;
                $rental = Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeAntwerpRotterdam,
                    'origin_port_id' => $antwerpId,
                    'destination_port_id' => $rotterdamId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 24,
                    'cargo_types' => ['food'],
                    'cargo_details' => 'Near discharge Antwerp–Rotterdam [demo-scenario:romeo-near-final]',
                    'priority' => 'urgent',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => true,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Demo Client',
                    'contact_phone' => '+10005550202',
                    'terms_accepted' => true,
                    'estimated_distance' => 190.0,
                    'price' => $price,
                    'price_breakdown' => [
                        'estimated_total' => $price,
                        'days' => 24,
                        'route_legs' => [[
                            'route_id' => (int) $routeAntwerpRotterdam,
                            'origin_port_id' => $antwerpId,
                            'destination_port_id' => $rotterdamId,
                            'estimated_days' => 1,
                            'distance' => 190.0,
                        ]],
                        'routing_mode' => 'time',
                    ],
                    'status' => 'in_progress',
                    'is_telemetry_active' => true,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'description' => 'Almost at Rotterdam (ETA 36h) [demo-scenario:romeo-near-final]',
                ]);

                $dep = $now->subDays(4);
                $arr = $now->addDays(2);
                $ship = Shipment::query()->create([
                    'vessel_id' => $v,
                    'route_id' => $routeAntwerpRotterdam,
                    'leg_sequence' => 1,
                    'departure_date' => $dep,
                    'arrival_date' => $arr,
                    'actual_departure_date' => $dep,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'in_transit',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo near-final leg',
                ]);
                $this->logRental($admin1->id, 'romeo_near_final_seeded', $rental->id, null, null, 'Seeded near-final arrival demo [demo-scenario:romeo-near-final]', $now);
            }
        }

        // --- Rental ending in 2 days, payment still pending (client2) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-ending-soon]%')->exists()) {
            $container = $this->pickContainerAtPort($rotterdamId);
            $v = $vesselAt($rotterdamId);
            if ($container && $v && $routeRotterdamLeHavre) {
                $container->update(['current_status' => 'in_use']);
                $start = $now->subDays(25);
                $end = $now->addDays(2);
                $price = 3600.0;
                $rental = Rental::query()->create([
                    'user_id' => $client2->id,
                    'container_id' => $container->id,
                    'route_id' => $routeRotterdamLeHavre,
                    'origin_port_id' => $rotterdamId,
                    'destination_port_id' => $leHavreId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 27,
                    'cargo_types' => ['electronics'],
                    'cargo_details' => 'Closing window — settle dues [demo-scenario:romeo-ending-soon]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => false,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => false,
                    'contact_name' => 'Elena Vogel',
                    'contact_phone' => '+49301234567',
                    'terms_accepted' => true,
                    'estimated_distance' => 620.0,
                    'price' => $price,
                    'price_breakdown' => [
                        'estimated_total' => $price,
                        'days' => 27,
                        'route_legs' => [[
                            'route_id' => (int) $routeRotterdamLeHavre,
                            'origin_port_id' => $rotterdamId,
                            'destination_port_id' => $leHavreId,
                            'estimated_days' => 3,
                            'distance' => 620.0,
                        ]],
                        'routing_mode' => 'cost',
                    ],
                    'status' => 'in_progress',
                    'is_telemetry_active' => true,
                    'payment_status' => 'pending',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'description' => 'Rental ends in 48h — payment pending [demo-scenario:romeo-ending-soon]',
                ]);

                $dep = $now->subDays(5);
                $ship = Shipment::query()->create([
                    'vessel_id' => $v,
                    'route_id' => $routeRotterdamLeHavre,
                    'leg_sequence' => 1,
                    'departure_date' => $dep,
                    'arrival_date' => $now->addDay(),
                    'actual_departure_date' => $dep,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'in_transit',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo ending-soon leg',
                ]);
                $this->logRental($admin1->id, 'romeo_ending_soon_seeded', $rental->id, null, null, 'Seeded ending-soon rental [demo-scenario:romeo-ending-soon]', $now);
            }
        }

        // --- Active + IoT metrics (demo client) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-active-telemetry]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            $v = $vesselAt($hamburgId);
            if ($container && $v && $routeHamburgRotterdam) {
                $container->update(['current_status' => 'in_use']);
                $start = $now->subDays(3);
                $end = $now->addDays(24);
                $price = 2950.0;
                $rental = Rental::query()->create([
                    'user_id' => $demoClient->id,
                    'container_id' => $container->id,
                    'route_id' => $routeHamburgRotterdam,
                    'origin_port_id' => $hamburgId,
                    'destination_port_id' => $rotterdamId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'rental_days' => 27,
                    'cargo_types' => ['other'],
                    'cargo_details' => 'Active status + sensor stream [demo-scenario:romeo-active-telemetry]',
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
                    'contact_phone' => '+10005550203',
                    'terms_accepted' => true,
                    'estimated_distance' => 430.0,
                    'price' => $price,
                    'price_breakdown' => [
                        'estimated_total' => $price,
                        'days' => 27,
                        'route_legs' => [[
                            'route_id' => (int) $routeHamburgRotterdam,
                            'origin_port_id' => $hamburgId,
                            'destination_port_id' => $rotterdamId,
                            'estimated_days' => 2,
                            'distance' => 430.0,
                        ]],
                        'routing_mode' => 'balanced',
                    ],
                    'status' => 'active',
                    'is_telemetry_active' => true,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'description' => 'Active rental with live telemetry [demo-scenario:romeo-active-telemetry]',
                ]);

                $dep = $now->subDay();
                $arr = $dep->addDays(3);
                $ship = Shipment::query()->create([
                    'vessel_id' => $v,
                    'route_id' => $routeHamburgRotterdam,
                    'leg_sequence' => 1,
                    'departure_date' => $dep,
                    'arrival_date' => $arr,
                    'actual_departure_date' => $dep,
                    'actual_arrival_date' => null,
                    'port_operations_until' => null,
                    'tracking_number' => $this->uniqueTracking(),
                    'status' => 'in_transit',
                ]);
                ShipmentItem::query()->create([
                    'shipment_id' => $ship->id,
                    'container_id' => $container->id,
                    'rental_id' => $rental->id,
                    'loaded_at' => $dep,
                    'condition_on_arrival' => 'good',
                    'notes' => 'Romeo active telemetry leg',
                ]);

                app(MetricsPartitionManager::class)->ensurePartitionForRentalId((int) $rental->id);
                foreach ([
                    ['temperature_c', 5.1, '°C', $now->subHours(2)],
                    ['humidity_pct', 72.0, '%', $now->subHour()],
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
                $this->logRental($admin1->id, 'romeo_active_telemetry_seeded', $rental->id, null, null, 'Seeded active + metrics [demo-scenario:romeo-active-telemetry]', $now);
            }
        }

        // --- Finance: completed + paid transaction (demo client) ---
        if (! Rental::query()->where('description', 'like', '%[demo-scenario:romeo-finance-paid]%')->exists()) {
            $container = $this->pickContainerAtPort($hamburgId);
            if ($container && $routeHamburgRotterdam) {
                $start = $now->subDays(90);
                $end = $now->subDays(55);
                $price = 2755.0;
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
                    'cargo_details' => 'Finance report row — paid closed [demo-scenario:romeo-finance-paid]',
                    'priority' => 'normal',
                    'loading_type' => 'fcl',
                    'delivery_mode' => 'port_to_port',
                    'sustainability_pref' => 'standard',
                    'insurance_required' => false,
                    'requires_customs_clearance' => false,
                    'hazardous_material' => false,
                    'requires_escort' => false,
                    'seal_required' => true,
                    'contact_name' => 'Demo Client',
                    'contact_phone' => '+10005550204',
                    'terms_accepted' => true,
                    'estimated_distance' => 430.0,
                    'price' => $price,
                    'price_breakdown' => [
                        'estimated_total' => $price,
                        'days' => 35,
                        'route_legs' => [[
                            'route_id' => (int) $routeHamburgRotterdam,
                            'origin_port_id' => $hamburgId,
                            'destination_port_id' => $rotterdamId,
                            'estimated_days' => 2,
                            'distance' => 430.0,
                        ]],
                        'routing_mode' => 'cost',
                    ],
                    'status' => 'completed',
                    'is_telemetry_active' => false,
                    'payment_status' => 'paid',
                    'reviewed_by' => $admin1->id,
                    'reviewed_at' => $start->subDay(),
                    'payment_approved_at' => $start,
                    'payment_approved_by' => $admin1->id,
                    'description' => 'Closed paid — admin finance slice [demo-scenario:romeo-finance-paid]',
                ]);
                $container->update(['current_port_id' => $rotterdamId, 'current_status' => 'available']);
                if (! Transaction::query()->where('rental_id', $rental->id)->exists()) {
                    Transaction::query()->firstOrCreate(
                        [
                            'rental_id' => $rental->id,
                            'external_provider_id' => 'demo-romeo-finance-paid-'.$rental->id,
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
                $this->logRental($admin1->id, 'romeo_finance_paid_seeded', $rental->id, null, null, 'Seeded finance completed rental [demo-scenario:romeo-finance-paid]', $now);
            }
        }
    }

    /**
     * Ten map-rich active rentals for demo@example.com: direct + 2-leg lanes, cross-region, paid + approved.
     */
    private function seedDemoClientMapPackRentals(): void
    {
        $mark = '[demo-scenario:demo-client-map-pack]';
        $admin1 = User::query()->where('email', 'romeobackend@gmail.com')->first();
        $demoClient = User::query()->where('email', 'demo@example.com')->first();
        if (! $admin1 || ! $demoClient) {
            return;
        }
        if (Rental::query()->where('user_id', $demoClient->id)->where('description', 'like', '%'.$mark.'%')->count() >= 10) {
            return;
        }

        $now = CarbonImmutable::now();
        $vesselAt = static fn (int $portId): ?int => Vessel::query()
            ->where('current_port_id', $portId)
            ->orderBy('id')
            ->value('id');

        $rotterdamId = $this->portId('Port of Rotterdam');
        $singaporeId = $this->portId('Port of Singapore');
        $losAngelesId = $this->portId('Port of Los Angeles');
        $yokohamaId = $this->portId('Port of Yokohama');
        $durbanId = $this->portId('Port of Durban');
        $newYorkId = $this->portId('Port of New York');
        $barcelonaId = $this->portId('Port of Barcelona');
        $hamburgId = $this->portId('Port of Hamburg');
        $antwerpId = $this->portId('Port of Antwerp');
        $gdanskId = $this->portId('Port of Gdansk');
        $valenciaId = $this->portId('Port of Valencia');
        $shanghaiId = $this->portId('Port of Shanghai');

        if (! $rotterdamId || ! $singaporeId || ! $losAngelesId || ! $yokohamaId || ! $durbanId || ! $newYorkId
            || ! $barcelonaId || ! $hamburgId || ! $antwerpId || ! $gdanskId || ! $valenciaId || ! $shanghaiId) {
            return;
        }

        $rRtmSg = $this->routeOpenId($rotterdamId, $singaporeId);
        $rLaYok = $this->routeOpenId($losAngelesId, $yokohamaId);
        $rDurSg = $this->routeOpenId($durbanId, $singaporeId);
        $rRtmNyc = $this->routeOpenId($rotterdamId, $newYorkId);
        $rBcnNyc = $this->routeOpenId($barcelonaId, $newYorkId);
        $rHamRtm = $this->routeOpenId($hamburgId, $rotterdamId);
        $rAntRtm = $this->routeOpenId($antwerpId, $rotterdamId);
        $rGdaRtm = $this->routeOpenId($gdanskId, $rotterdamId);
        $rValBcn = $this->routeOpenId($valenciaId, $barcelonaId);
        $rSgSha = $this->routeOpenId($singaporeId, $shanghaiId);

        if (! $rRtmSg || ! $rLaYok || ! $rDurSg || ! $rRtmNyc || ! $rBcnNyc || ! $rHamRtm || ! $rAntRtm || ! $rGdaRtm || ! $rValBcn || ! $rSgSha) {
            return;
        }

        $mapPackTxn = static function (Rental $rental, float $amount, CarbonImmutable $txAt): void {
            if (Transaction::query()->where('rental_id', $rental->id)->exists()) {
                return;
            }
            Transaction::query()->create([
                'rental_id' => $rental->id,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'paid',
                'transaction_date' => $txAt,
                'payment_method' => 'card',
                'external_provider_id' => 'demo-map-pack-'.$rental->id,
            ]);
        };

        // 1) Direct: Rotterdam → Singapore (in progress, at sea)
        $c = $this->pickContainerAtPort($rotterdamId);
        $v = $vesselAt($rotterdamId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(8);
            $end = $now->addDays(55);
            $price = 11800.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rRtmSg,
                'origin_port_id' => $rotterdamId,
                'destination_port_id' => $singaporeId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 62,
                'cargo_types' => ['electronics'],
                'cargo_details' => 'Map pack EU→Asia direct '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => true,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550901',
                'terms_accepted' => true,
                'estimated_distance' => 10600.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 62,
                    'route_legs' => [[
                        'route_id' => $rRtmSg,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $singaporeId,
                        'estimated_days' => 28,
                        'distance' => 10600.0,
                    ]],
                    'routing_mode' => 'balanced',
                ],
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 1 · Rotterdam → Singapore '.$mark,
            ]);
            $dep = $now->subDays(3);
            $arr = $now->addDays(22);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rRtmSg,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack leg 1',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 2) Direct: Los Angeles → Yokohama (active)
        $c = $this->pickContainerAtPort($losAngelesId);
        $v = $vesselAt($losAngelesId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(6);
            $end = $now->addDays(40);
            $price = 9200.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rLaYok,
                'origin_port_id' => $losAngelesId,
                'destination_port_id' => $yokohamaId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 46,
                'cargo_types' => ['furniture'],
                'cargo_details' => 'Map pack transpacific active '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => false,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550902',
                'terms_accepted' => true,
                'estimated_distance' => 8900.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 46,
                    'route_legs' => [[
                        'route_id' => $rLaYok,
                        'origin_port_id' => $losAngelesId,
                        'destination_port_id' => $yokohamaId,
                        'estimated_days' => 14,
                        'distance' => 8900.0,
                    ]],
                    'routing_mode' => 'balanced',
                ],
                'status' => 'active',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 2 · Los Angeles → Yokohama '.$mark,
            ]);
            $dep = $now->subDays(2);
            $arr = $now->addDays(11);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rLaYok,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack transpacific',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 3) Direct: Durban → Singapore
        $c = $this->pickContainerAtPort($durbanId);
        $v = $vesselAt($durbanId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(10);
            $end = $now->addDays(35);
            $price = 8900.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rDurSg,
                'origin_port_id' => $durbanId,
                'destination_port_id' => $singaporeId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 45,
                'cargo_types' => ['machinery'],
                'cargo_details' => 'Map pack Indian Ocean lane '.$mark,
                'priority' => 'urgent',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => true,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550903',
                'terms_accepted' => true,
                'estimated_distance' => 8300.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 45,
                    'route_legs' => [[
                        'route_id' => $rDurSg,
                        'origin_port_id' => $durbanId,
                        'destination_port_id' => $singaporeId,
                        'estimated_days' => 25,
                        'distance' => 8300.0,
                    ]],
                    'routing_mode' => 'speed',
                ],
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 3 · Durban → Singapore '.$mark,
            ]);
            $dep = $now->subDays(4);
            $arr = $now->addDays(18);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rDurSg,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack Durban',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 4) Direct: Rotterdam → New York
        $c = $this->pickContainerAtPort($rotterdamId);
        $v = $vesselAt($rotterdamId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(7);
            $end = $now->addDays(42);
            $price = 6400.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rRtmNyc,
                'origin_port_id' => $rotterdamId,
                'destination_port_id' => $newYorkId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 49,
                'cargo_types' => ['clothing'],
                'cargo_details' => 'Map pack transatlantic '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'eco_optimized',
                'insurance_required' => false,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550904',
                'terms_accepted' => true,
                'estimated_distance' => 5850.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 49,
                    'route_legs' => [[
                        'route_id' => $rRtmNyc,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $newYorkId,
                        'estimated_days' => 22,
                        'distance' => 5850.0,
                    ]],
                    'routing_mode' => 'cost',
                ],
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 4 · Rotterdam → New York '.$mark,
            ]);
            $dep = $now->subDays(2);
            $arr = $now->addDays(17);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rRtmNyc,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack RTM-NYC',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 5) Direct: Barcelona → New York (scheduled + paid, future start)
        $c = $this->pickContainerAtPort($barcelonaId);
        $v = $vesselAt($barcelonaId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->addDays(12);
            $end = $now->addDays(48);
            $price = 7100.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rBcnNyc,
                'origin_port_id' => $barcelonaId,
                'destination_port_id' => $newYorkId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 36,
                'cargo_types' => ['food'],
                'cargo_details' => 'Map pack reefer scheduled '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => true,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550905',
                'terms_accepted' => true,
                'estimated_distance' => 6600.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 36,
                    'route_legs' => [[
                        'route_id' => $rBcnNyc,
                        'origin_port_id' => $barcelonaId,
                        'destination_port_id' => $newYorkId,
                        'estimated_days' => 18,
                        'distance' => 6600.0,
                    ]],
                    'routing_mode' => 'balanced',
                ],
                'status' => 'scheduled',
                'is_telemetry_active' => false,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $now->subDays(2),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $now->subDay(),
                'description' => 'Map pack 5 · Barcelona → New York (scheduled) '.$mark,
            ]);
            $dep = $start;
            $arr = $start->addDays(20);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rBcnNyc,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => null,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'scheduled',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack BCN-NYC scheduled',
            ]);
            $mapPackTxn($rental, $price, $now->subDay());
        }

        // 6) 2-leg: Hamburg → Rotterdam → Singapore (leg1 completed, leg2 in transit)
        $c = $this->pickContainerAtPort($hamburgId);
        $v1 = $vesselAt($hamburgId);
        $v2 = $vesselAt($rotterdamId);
        if ($c && $v1 && $v2) {
            $c->update(['current_status' => 'in_use', 'current_port_id' => $rotterdamId]);
            $start = $now->subDays(20);
            $end = $now->addDays(38);
            $price = 12400.0;
            $pb = [
                'estimated_total' => $price,
                'days' => 58,
                'route_legs' => [
                    [
                        'route_id' => $rHamRtm,
                        'origin_port_id' => $hamburgId,
                        'destination_port_id' => $rotterdamId,
                        'estimated_days' => 2,
                        'distance' => 430.0,
                    ],
                    [
                        'route_id' => $rRtmSg,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $singaporeId,
                        'estimated_days' => 28,
                        'distance' => 10600.0,
                    ],
                ],
                'routing_mode' => 'balanced',
            ];
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rRtmSg,
                'origin_port_id' => $hamburgId,
                'destination_port_id' => $singaporeId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 58,
                'cargo_types' => ['electronics'],
                'cargo_details' => 'Map pack 2-leg via Rotterdam '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => true,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550906',
                'terms_accepted' => true,
                'estimated_distance' => 11030.0,
                'price' => $price,
                'price_breakdown' => $pb,
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 6 · HAM→RTM→SIN '.$mark,
            ]);
            $l1d = $now->subDays(14);
            $l1a = $now->subDays(11);
            $ship1 = Shipment::query()->create([
                'vessel_id' => $v1,
                'route_id' => $rHamRtm,
                'leg_sequence' => 1,
                'departure_date' => $l1d,
                'arrival_date' => $l1a,
                'actual_departure_date' => $l1d,
                'actual_arrival_date' => $l1a,
                'port_operations_until' => $now->subDays(9),
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'completed',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship1->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l1d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack leg1 done',
            ]);
            $l2d = $now->subDays(8);
            $l2a = $now->addDays(16);
            $ship2 = Shipment::query()->create([
                'vessel_id' => $v2,
                'route_id' => $rRtmSg,
                'leg_sequence' => 2,
                'departure_date' => $l2d,
                'arrival_date' => $l2a,
                'actual_departure_date' => $l2d,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship2->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l2d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack leg2 sea',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 7) 2-leg: Antwerp → Rotterdam → Singapore (leg1 in transit, leg2 scheduled)
        $c = $this->pickContainerAtPort($antwerpId);
        $v1 = $vesselAt($antwerpId);
        $v2 = $vesselAt($rotterdamId);
        if ($c && $v1 && $v2) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(5);
            $end = $now->addDays(50);
            $price = 11900.0;
            $pb = [
                'estimated_total' => $price,
                'days' => 55,
                'route_legs' => [
                    [
                        'route_id' => $rAntRtm,
                        'origin_port_id' => $antwerpId,
                        'destination_port_id' => $rotterdamId,
                        'estimated_days' => 1,
                        'distance' => 190.0,
                    ],
                    [
                        'route_id' => $rRtmSg,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $singaporeId,
                        'estimated_days' => 28,
                        'distance' => 10600.0,
                    ],
                ],
                'routing_mode' => 'balanced',
            ];
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rRtmSg,
                'origin_port_id' => $antwerpId,
                'destination_port_id' => $singaporeId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 55,
                'cargo_types' => ['other'],
                'cargo_details' => 'Map pack proxy lane ANR→RTM→SIN '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => false,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550907',
                'terms_accepted' => true,
                'estimated_distance' => 10790.0,
                'price' => $price,
                'price_breakdown' => $pb,
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 7 · ANR→RTM→SIN '.$mark,
            ]);
            $l1d = $now->subDays(1);
            $l1a = $now->addDays(1);
            $ship1 = Shipment::query()->create([
                'vessel_id' => $v1,
                'route_id' => $rAntRtm,
                'leg_sequence' => 1,
                'departure_date' => $l1d,
                'arrival_date' => $l1a,
                'actual_departure_date' => $l1d,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship1->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l1d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack ANR-RTM underway',
            ]);
            $l2d = $now->addDays(4);
            $l2a = $now->addDays(30);
            $ship2 = Shipment::query()->create([
                'vessel_id' => $v2,
                'route_id' => $rRtmSg,
                'leg_sequence' => 2,
                'departure_date' => $l2d,
                'arrival_date' => $l2a,
                'actual_departure_date' => null,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'scheduled',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship2->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l2d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack RTM-SIN booked',
            ]);
            $ship1->touch();
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 8) 2-leg: Gdansk → Rotterdam → New York
        $c = $this->pickContainerAtPort($gdanskId);
        $v1 = $vesselAt($gdanskId);
        $v2 = $vesselAt($rotterdamId);
        if ($c && $v1 && $v2) {
            $c->update(['current_status' => 'in_use', 'current_port_id' => $rotterdamId]);
            $start = $now->subDays(18);
            $end = $now->addDays(32);
            $price = 7200.0;
            $pb = [
                'estimated_total' => $price,
                'days' => 50,
                'route_legs' => [
                    [
                        'route_id' => $rGdaRtm,
                        'origin_port_id' => $gdanskId,
                        'destination_port_id' => $rotterdamId,
                        'estimated_days' => 3,
                        'distance' => 1180.0,
                    ],
                    [
                        'route_id' => $rRtmNyc,
                        'origin_port_id' => $rotterdamId,
                        'destination_port_id' => $newYorkId,
                        'estimated_days' => 22,
                        'distance' => 5850.0,
                    ],
                ],
                'routing_mode' => 'cost',
            ];
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rRtmNyc,
                'origin_port_id' => $gdanskId,
                'destination_port_id' => $newYorkId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 50,
                'cargo_types' => ['furniture'],
                'cargo_details' => 'Map pack Baltic→US '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => false,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550908',
                'terms_accepted' => true,
                'estimated_distance' => 7030.0,
                'price' => $price,
                'price_breakdown' => $pb,
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 8 · GDN→RTM→NYC '.$mark,
            ]);
            $l1d = $now->subDays(12);
            $l1a = $now->subDays(8);
            $ship1 = Shipment::query()->create([
                'vessel_id' => $v1,
                'route_id' => $rGdaRtm,
                'leg_sequence' => 1,
                'departure_date' => $l1d,
                'arrival_date' => $l1a,
                'actual_departure_date' => $l1d,
                'actual_arrival_date' => $l1a,
                'port_operations_until' => $now->subDays(6),
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'completed',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship1->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l1d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack GDN-RTM done',
            ]);
            $l2d = $now->subDays(5);
            $l2a = $now->addDays(14);
            $ship2 = Shipment::query()->create([
                'vessel_id' => $v2,
                'route_id' => $rRtmNyc,
                'leg_sequence' => 2,
                'departure_date' => $l2d,
                'arrival_date' => $l2a,
                'actual_departure_date' => $l2d,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship2->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l2d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack RTM-NYC sea',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 9) 2-leg: Valencia → Barcelona → New York
        $c = $this->pickContainerAtPort($valenciaId);
        $v1 = $vesselAt($valenciaId);
        $v2 = $vesselAt($barcelonaId);
        if ($c && $v1 && $v2) {
            $c->update(['current_status' => 'in_use', 'current_port_id' => $barcelonaId]);
            $start = $now->subDays(9);
            $end = $now->addDays(28);
            $price = 6950.0;
            $pb = [
                'estimated_total' => $price,
                'days' => 37,
                'route_legs' => [
                    [
                        'route_id' => $rValBcn,
                        'origin_port_id' => $valenciaId,
                        'destination_port_id' => $barcelonaId,
                        'estimated_days' => 1,
                        'distance' => 380.0,
                    ],
                    [
                        'route_id' => $rBcnNyc,
                        'origin_port_id' => $barcelonaId,
                        'destination_port_id' => $newYorkId,
                        'estimated_days' => 18,
                        'distance' => 6600.0,
                    ],
                ],
                'routing_mode' => 'balanced',
            ];
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rBcnNyc,
                'origin_port_id' => $valenciaId,
                'destination_port_id' => $newYorkId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 37,
                'cargo_types' => ['clothing'],
                'cargo_details' => 'Map pack Med feeder + TA '.$mark,
                'priority' => 'normal',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => false,
                'requires_customs_clearance' => true,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550909',
                'terms_accepted' => true,
                'estimated_distance' => 6980.0,
                'price' => $price,
                'price_breakdown' => $pb,
                'status' => 'in_progress',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 9 · VLC→BCN→NYC '.$mark,
            ]);
            $l1d = $now->subDays(7);
            $l1a = $now->subDays(6);
            $ship1 = Shipment::query()->create([
                'vessel_id' => $v1,
                'route_id' => $rValBcn,
                'leg_sequence' => 1,
                'departure_date' => $l1d,
                'arrival_date' => $l1a,
                'actual_departure_date' => $l1d,
                'actual_arrival_date' => $l1a,
                'port_operations_until' => $now->subDays(5),
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'completed',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship1->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l1d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack VLC-BCN',
            ]);
            $l2d = $now->subDays(4);
            $l2a = $now->addDays(13);
            $ship2 = Shipment::query()->create([
                'vessel_id' => $v2,
                'route_id' => $rBcnNyc,
                'leg_sequence' => 2,
                'departure_date' => $l2d,
                'arrival_date' => $l2a,
                'actual_departure_date' => $l2d,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship2->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $l2d,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack BCN-NYC',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
        }

        // 10) Direct: Singapore → Shanghai (active + telemetry + metrics)
        $c = $this->pickContainerAtPort($singaporeId);
        $v = $vesselAt($singaporeId);
        if ($c && $v) {
            $c->update(['current_status' => 'in_use']);
            $start = $now->subDays(4);
            $end = $now->addDays(22);
            $price = 3100.0;
            $rental = Rental::query()->create([
                'user_id' => $demoClient->id,
                'container_id' => $c->id,
                'route_id' => $rSgSha,
                'origin_port_id' => $singaporeId,
                'destination_port_id' => $shanghaiId,
                'start_date' => $start,
                'end_date' => $end,
                'rental_days' => 26,
                'cargo_types' => ['electronics'],
                'cargo_details' => 'Map pack intra-Asia telemetry '.$mark,
                'priority' => 'express',
                'loading_type' => 'fcl',
                'delivery_mode' => 'port_to_port',
                'sustainability_pref' => 'standard',
                'insurance_required' => true,
                'requires_customs_clearance' => false,
                'hazardous_material' => false,
                'requires_escort' => false,
                'seal_required' => true,
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550910',
                'terms_accepted' => true,
                'estimated_distance' => 2800.0,
                'price' => $price,
                'price_breakdown' => [
                    'estimated_total' => $price,
                    'days' => 26,
                    'route_legs' => [[
                        'route_id' => $rSgSha,
                        'origin_port_id' => $singaporeId,
                        'destination_port_id' => $shanghaiId,
                        'estimated_days' => 5,
                        'distance' => 2800.0,
                    ]],
                    'routing_mode' => 'speed',
                ],
                'status' => 'active',
                'is_telemetry_active' => true,
                'payment_status' => 'paid',
                'reviewed_by' => $admin1->id,
                'reviewed_at' => $start->subDay(),
                'payment_approved_by' => $admin1->id,
                'payment_approved_at' => $start,
                'description' => 'Map pack 10 · Singapore → Shanghai '.$mark,
            ]);
            $dep = $now->subDays(1);
            $arr = $now->addDays(4);
            $ship = Shipment::query()->create([
                'vessel_id' => $v,
                'route_id' => $rSgSha,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $this->uniqueTracking(),
                'status' => 'in_transit',
            ]);
            ShipmentItem::query()->create([
                'shipment_id' => $ship->id,
                'container_id' => $c->id,
                'rental_id' => $rental->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Map pack SG-SHA',
            ]);
            $mapPackTxn($rental, $price, $start->addDays(1));
            app(MetricsPartitionManager::class)->ensurePartitionForRentalId((int) $rental->id);
            foreach ([
                ['temperature_c', 6.2, '°C', $now->subHours(3)],
                ['humidity_pct', 68.0, '%', $now->subHours(1)],
            ] as $row) {
                Metric::query()->create([
                    'container_id' => $c->id,
                    'rental_id' => $rental->id,
                    'type' => $row[0],
                    'value' => $row[1],
                    'unit' => $row[2],
                    'recorded_at' => $row[3],
                ]);
            }
        }
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
            ->limit(36)
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
