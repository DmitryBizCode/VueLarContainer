<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo rentals + shipments for the demo client (map, rentals center, logistics UI).
 */
class MaritimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('rentals')->where('description', 'like', '%[demo-seed]%')->exists()) {
            return;
        }

        $demoUserId = DB::table('users')->where('email', 'demo@example.com')->value('id');
        $adminUserId = DB::table('users')->where('email', 'romeobackend@gmail.com')->value('id');

        if (! $demoUserId) {
            return;
        }

        $hamburgId = DB::table('ports')->where('name', 'Port of Hamburg')->value('id');
        $rotterdamId = DB::table('ports')->where('name', 'Port of Rotterdam')->value('id');
        $valenciaId = DB::table('ports')->where('name', 'Port of Valencia')->value('id');

        if (! $hamburgId || ! $rotterdamId || ! $valenciaId) {
            return;
        }

        $routeId = DB::table('routes')
            ->where('origin_port_id', $hamburgId)
            ->where('destination_port_id', $rotterdamId)
            ->where('route_status', 'open')
            ->value('id');

        $containerId = DB::table('containers')
            ->where('current_port_id', $hamburgId)
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->orderBy('id')
            ->value('id');

        $vesselId = DB::table('vessels')
            ->where('current_port_id', $hamburgId)
            ->orderBy('id')
            ->value('id');

        if (! $containerId || ! $routeId) {
            return;
        }

        $now = now();
        $cargo = json_encode(['electronics']);
        $legs = json_encode([
            [
                'route_id' => (int) $routeId,
                'origin_port_id' => (int) $hamburgId,
                'destination_port_id' => (int) $rotterdamId,
                'estimated_days' => 2,
                'distance' => 430.0,
            ],
        ]);
        $priceBreakdown = json_encode([
            'estimated_total' => 2850.5,
            'days' => 30,
            'route_legs' => json_decode($legs, true),
            'routing_mode' => 'time',
        ]);

        DB::table('containers')->where('id', $containerId)->update([
            'current_status' => 'in_use',
            'updated_at' => $now,
        ]);

        $rentalActiveId = DB::table('rentals')->insertGetId([
            'user_id' => $demoUserId,
            'container_id' => $containerId,
            'route_id' => $routeId,
            'origin_port_id' => $hamburgId,
            'destination_port_id' => $rotterdamId,
            'start_date' => $now->copy()->subDays(4),
            'end_date' => $now->copy()->addDays(26),
            'rental_days' => 30,
            'cargo_types' => $cargo,
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
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($vesselId) {
            $tracking = $this->uniqueTracking();
            $dep = $now->copy()->subDays(2);
            $arr = $dep->copy()->addDays(2);
            $shipmentId = DB::table('shipments')->insertGetId([
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('shipment_items')->insert([
                'shipment_id' => $shipmentId,
                'container_id' => $containerId,
                'rental_id' => $rentalActiveId,
                'loaded_at' => $dep,
                'condition_on_arrival' => 'good',
                'notes' => 'Seeded with MaritimeDemoSeeder',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $containerPending = DB::table('containers')
            ->where('current_port_id', $rotterdamId)
            ->where('serial_number', 'like', 'VL-SEED-%')
            ->where('id', '!=', $containerId)
            ->orderBy('id')
            ->value('id');

        if ($containerPending) {
            DB::table('rentals')->insert([
                'user_id' => $demoUserId,
                'container_id' => $containerPending,
                'route_id' => $routeId,
                'origin_port_id' => $rotterdamId,
                'destination_port_id' => $valenciaId,
                'start_date' => $now->copy()->addDays(7),
                'end_date' => $now->copy()->addDays(40),
                'rental_days' => 33,
                'cargo_types' => $cargo,
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
                'price_breakdown' => json_encode(['estimated_total' => 4100.0, 'days' => 33, 'route_legs' => [], 'routing_mode' => 'time']),
                'status' => 'pending_approval',
                'is_telemetry_active' => true,
                'payment_status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'description' => 'Seeded pending approval Rotterdam → Valencia [demo-seed]',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function uniqueTracking(): string
    {
        do {
            $t = 'DEMO-'.strtoupper(Str::random(10));
        } while (DB::table('shipments')->where('tracking_number', $t)->exists());

        return $t;
    }
}
