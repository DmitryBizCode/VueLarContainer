<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo rentals + shipments for the demo client (map, rentals center, logistics UI).
 */
class MaritimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Rental::query()->where('description', 'like', '%[demo-seed]%')->exists()) {
            return;
        }

        $demoUserId = User::query()->where('email', 'demo@example.com')->value('id');
        $adminUserId = User::query()->where('email', 'romeobackend@gmail.com')->value('id');

        if (! $demoUserId) {
            return;
        }

        $hamburgId = Port::query()->where('name', 'Port of Hamburg')->value('id');
        $rotterdamId = Port::query()->where('name', 'Port of Rotterdam')->value('id');
        $valenciaId = Port::query()->where('name', 'Port of Valencia')->value('id');

        if (! $hamburgId || ! $rotterdamId || ! $valenciaId) {
            return;
        }

        $routeId = Route::query()
            ->where('origin_port_id', $hamburgId)
            ->where('destination_port_id', $rotterdamId)
            ->where('route_status', 'open')
            ->value('id');

        $containerId = Container::query()
            ->where('current_port_id', $hamburgId)
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->orderBy('id')
            ->value('id');

        $vesselId = Vessel::query()
            ->where('current_port_id', $hamburgId)
            ->orderBy('id')
            ->value('id');

        if (! $containerId || ! $routeId) {
            return;
        }

        $now = now();
        $routeLegs = [
            [
                'route_id' => (int) $routeId,
                'origin_port_id' => (int) $hamburgId,
                'destination_port_id' => (int) $rotterdamId,
                'estimated_days' => 2,
                'distance' => 430.0,
            ],
        ];
        $priceBreakdown = [
            'estimated_total' => 2850.5,
            'days' => 30,
            'route_legs' => $routeLegs,
            'routing_mode' => 'time',
        ];

        Container::query()->whereKey($containerId)->update([
            'current_status' => 'in_use',
        ]);

        $rentalActive = Rental::query()->create([
            'user_id' => $demoUserId,
            'container_id' => $containerId,
            'route_id' => $routeId,
            'origin_port_id' => $hamburgId,
            'destination_port_id' => $rotterdamId,
            'start_date' => $now->copy()->subDays(4),
            'end_date' => $now->copy()->addDays(26),
            'rental_days' => 30,
            'cargo_types' => ['electronics'],
            'cargo_details' => 'Seeded demo cargo [demo-seed]',
            'priority' => 'normal',
            'routing_priority' => null,
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
            'price_breakdown' => $priceBreakdown,
            'status' => 'in_progress',
            'is_telemetry_active' => true,
            'payment_status' => 'paid',
            'reviewed_by' => $adminUserId,
            'reviewed_at' => $now,
            'description' => 'Auto-seeded active leg Hamburg → Rotterdam [demo-seed]',
        ]);

        if ($vesselId) {
            $tracking = $this->uniqueTracking();
            $dep = $now->copy()->subDays(2);
            $arr = $dep->copy()->addDays(2);
            $shipment = Shipment::query()->create([
                'vessel_id' => $vesselId,
                'route_id' => $routeId,
                'leg_sequence' => 1,
                'departure_date' => $dep,
                'arrival_date' => $arr,
                'actual_departure_date' => $dep,
                'actual_arrival_date' => null,
                'port_operations_until' => null,
                'tracking_number' => $tracking,
                'status' => 'in_transit',
            ]);

            ShipmentItem::query()->create([
                'shipment_id' => $shipment->id,
                'container_id' => $containerId,
                'rental_id' => $rentalActive->id,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Seeded with MaritimeDemoSeeder',
            ]);
        }

        $containerPending = Container::query()
            ->where('current_port_id', $rotterdamId)
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->where('id', '!=', $containerId)
            ->orderBy('id')
            ->value('id');

        if ($containerPending) {
            Rental::query()->create([
                'user_id' => $demoUserId,
                'container_id' => $containerPending,
                'route_id' => $routeId,
                'origin_port_id' => $rotterdamId,
                'destination_port_id' => $valenciaId,
                'start_date' => $now->copy()->addDays(7),
                'end_date' => $now->copy()->addDays(40),
                'rental_days' => 33,
                'cargo_types' => ['electronics'],
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
                'contact_name' => 'Demo Client',
                'contact_phone' => '+10005550199',
                'terms_accepted' => true,
                'estimated_distance' => 1100.0,
                'price' => 4100.0,
                'price_breakdown' => ['estimated_total' => 4100.0, 'days' => 33, 'route_legs' => [], 'routing_mode' => 'time'],
                'status' => 'pending_approval',
                'is_telemetry_active' => true,
                'payment_status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'description' => 'Seeded pending approval Rotterdam → Valencia [demo-seed]',
            ]);
        }
    }

    private function uniqueTracking(): string
    {
        do {
            $t = 'DEMO-'.strtoupper(Str::random(10));
        } while (Shipment::query()->where('tracking_number', $t)->exists());

        return $t;
    }
}
