<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Metric;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\User;
use App\Services\TelemetryAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonitorChartsTelemetryScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Worker often persists metrics with rental_id NULL when no active rental was resolved at tick time.
     * Monitor charts for a rental must still show those container rows (same container).
     */
    public function test_chart_series_treats_null_rental_metrics_as_visible_for_rental(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Scopeland',
            'iso_code' => 'SC',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Scope Owner',
            'email' => 'scope-owner@test.local',
            'phone_number' => '+1000000000',
        ]);
        $port = Port::query()->create([
            'name' => 'Scope Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'SCOPE-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $now = CarbonImmutable::now();
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => null,
            'type' => 'temperature_c',
            'value' => 4.2,
            'unit' => '°C',
            'recorded_at' => $now,
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $now->subHour(),
            $now->addHour(),
            (int) $rental->id
        );

        $this->assertTrue($chart['telemetry_backed']);
        $this->assertArrayHasKey('stats', $chart);
        $this->assertNotEmpty($chart['series']);
        $this->assertSame(1, $chart['samples_in_range']);
        $last = end($chart['series']);
        $this->assertSame(4.2, $last['value']);
    }

    public function test_chart_series_time_buckets_cover_full_window_with_median_aggregation(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Tailend',
            'iso_code' => 'TE',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Tail Owner',
            'email' => 'tail-owner@test.local',
            'phone_number' => '+1000000001',
        ]);
        $port = Port::query()->create([
            'name' => 'Tail Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'TAIL-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $base = CarbonImmutable::now()->subHours(2);
        for ($i = 1; $i <= 40; $i++) {
            Metric::query()->create([
                'container_id' => $container->id,
                'rental_id' => $rental->id,
                'type' => 'temperature_c',
                'value' => (float) $i,
                'unit' => '°C',
                'recorded_at' => $base->addMinutes($i),
            ]);
        }

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $base,
            $base->addHours(6),
            (int) $rental->id
        );

        $this->assertCount(30, $chart['series']);
        $this->assertSame(40, $chart['samples_in_range']);
        $this->assertSame(30, $chart['chart_max_points']);
        $this->assertFalse($chart['stats_truncated']);
        $this->assertFalse($chart['discrete']);
        $this->assertSame(40, $chart['stats']['count']);
        $this->assertEqualsWithDelta(20.5, $chart['stats']['mean'], 0.0001);
        // 30 equal buckets over 6 h; first bucket holds minutes 1–11 → median 6; last buckets forward-fill from bucket 3 (minutes 36–40 → median 38).
        $this->assertSame(6.0, $chart['series'][0]['value']);
        $this->assertSame(38.0, $chart['series'][29]['value']);
    }

    public function test_chart_series_discrete_bucket_uses_max_per_bucket_for_pump(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Pumpbucket',
            'iso_code' => 'PB',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Pump Owner',
            'email' => 'pump-owner@test.local',
            'phone_number' => '+1000000005',
        ]);
        $port = Port::query()->create([
            'name' => 'Pump Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'PUMP-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $prevCap = config('iot_monitor.chart_max_points');
        config(['iot_monitor.chart_max_points' => 10]);

        $from = CarbonImmutable::parse('2026-04-01 12:00:00');
        $to = $from->addHours(10);
        $bucketWidthSec = 10 * 3600 / 10; // 3600 s = 1 h per bucket
        $pulseAt = $from->addSeconds((int) (4 * $bucketWidthSec + $bucketWidthSec * 0.5));

        for ($h = 0; $h < 10; $h++) {
            Metric::query()->create([
                'container_id' => $container->id,
                'rental_id' => $rental->id,
                'type' => 'pump_running',
                'value' => 0.0,
                'unit' => '',
                'recorded_at' => $from->addHours($h),
            ]);
        }
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'pump_running',
            'value' => 1.0,
            'unit' => '',
            'recorded_at' => $pulseAt,
        ]);

        try {
            $svc = app(TelemetryAnalyticsService::class);
            $chart = $svc->chartSeriesForMonitor(
                (int) $container->id,
                'pump_running',
                $from,
                $to,
                (int) $rental->id
            );

            $this->assertTrue($chart['discrete']);
            $this->assertCount(10, $chart['series']);
            $this->assertSame(1.0, $chart['series'][4]['value']);
            $this->assertSame(11, $chart['stats']['count']);
        } finally {
            config(['iot_monitor.chart_max_points' => $prevCap]);
        }
    }

    public function test_door_open_window_series_is_state_transitions_not_buckets(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Doorjump',
            'iso_code' => 'DJ',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Jump Owner',
            'email' => 'jump-owner@test.local',
            'phone_number' => '+1000000006',
        ]);
        $port = Port::query()->create([
            'name' => 'Jump Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'DJ-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $from = CarbonImmutable::parse('2026-05-01 10:00:00');
        $to = $from->addHours(2);
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'door_open',
            'value' => 0.0,
            'unit' => '',
            'recorded_at' => $from->addMinutes(10),
        ]);
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'door_open',
            'value' => 1.0,
            'unit' => '',
            'recorded_at' => $from->addMinutes(40),
        ]);
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'door_open',
            'value' => 0.0,
            'unit' => '',
            'recorded_at' => $from->addMinutes(70),
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'door_open',
            $from,
            $to,
            (int) $rental->id,
            null,
            'window'
        );

        $this->assertTrue($chart['discrete']);
        $this->assertSame(3, $chart['stats']['count']);
        $vals = array_map(static fn (array $p) => $p['value'], $chart['series']);
        $this->assertContains(1.0, $vals);
        $this->assertLessThanOrEqual(8, count($chart['series']));
    }

    public function test_chart_series_raw_tail_caps_at_chart_max_points(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Tailland',
            'iso_code' => 'TL',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Tail Owner',
            'email' => 'tail-owner@test.local',
            'phone_number' => '+1000000007',
        ]);
        $port = Port::query()->create([
            'name' => 'Tail Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'TAIL-2',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $base = CarbonImmutable::parse('2026-06-01 08:00:00');
        for ($i = 0; $i < 45; $i++) {
            Metric::query()->create([
                'container_id' => $container->id,
                'rental_id' => $rental->id,
                'type' => 'temperature_c',
                'value' => (float) $i,
                'unit' => '°C',
                'recorded_at' => $base->addMinutes($i),
            ]);
        }

        $svc = app(TelemetryAnalyticsService::class);
        $to = $base->addHours(2);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $base,
            $to,
            (int) $rental->id,
            null,
            'raw_tail'
        );

        $this->assertCount(30, $chart['series']);
        $this->assertSame(30, $chart['stats']['count']);
        $this->assertSame(45, $chart['samples_in_range']);
        $this->assertTrue($chart['stats_truncated']);
        $this->assertSame(44.0, end($chart['series'])['value']);
    }

    public function test_chart_series_includes_metrics_for_sibling_rental_same_lessee_on_container(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Siblingland',
            'iso_code' => 'SB',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Sibling Owner',
            'email' => 'sibling-owner@test.local',
            'phone_number' => '+1000000002',
        ]);
        $port = Port::query()->create([
            'name' => 'Sibling Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'SIB-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rentalOlder = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);
        $rentalNewer = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $now = CarbonImmutable::now();
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rentalNewer->id,
            'type' => 'temperature_c',
            'value' => 7.7,
            'unit' => '°C',
            'recorded_at' => $now,
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $now->subHour(),
            $now->addHour(),
            (int) $rentalOlder->id,
            (int) $user->id
        );

        $this->assertTrue($chart['telemetry_backed']);
        $this->assertSame(7.7, end($chart['series'])['value']);
    }

    public function test_chart_series_excludes_other_lessees_rental_metrics(): void
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Otherland',
            'iso_code' => 'OT',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'Other Owner',
            'email' => 'other-owner@test.local',
            'phone_number' => '+1000000003',
        ]);
        $port = Port::query()->create([
            'name' => 'Other Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'OTH-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $rentalA = Rental::query()->create([
            'user_id' => $userA->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);
        $rentalB = Rental::query()->create([
            'user_id' => $userB->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        $now = CarbonImmutable::now();
        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rentalB->id,
            'type' => 'temperature_c',
            'value' => 99.0,
            'unit' => '°C',
            'recorded_at' => $now,
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $now->subHour(),
            $now->addHour(),
            (int) $rentalA->id,
            (int) $userA->id
        );

        $this->assertFalse($chart['telemetry_backed']);
        $this->assertSame(0, $chart['samples_in_range']);
    }

    public function test_chart_series_uses_extended_lookback_when_strict_window_empty(): void
    {
        [$container, $rental, $user] = $this->createScopedFixtures();
        $now = CarbonImmutable::now();

        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'temperature_c',
            'value' => 9.9,
            'unit' => '°C',
            'recorded_at' => $now->subDays(3),
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $now->subHour(),
            $now,
            (int) $rental->id,
            (int) $user->id
        );

        $this->assertTrue($chart['telemetry_backed']);
        $this->assertTrue($chart['used_extended_lookback']);
        $this->assertSame(0, $chart['samples_in_range']);
        $this->assertNotEmpty($chart['series']);
        $last = end($chart['series']);
        $this->assertEqualsWithDelta(9.9, $last['value'], 0.001);
    }

    public function test_chart_series_extended_lookback_does_not_load_rows_older_than_config(): void
    {
        [$container, $rental, $user] = $this->createScopedFixtures();
        $now = CarbonImmutable::now();

        Metric::query()->create([
            'container_id' => $container->id,
            'rental_id' => $rental->id,
            'type' => 'temperature_c',
            'value' => 88.0,
            'unit' => '°C',
            'recorded_at' => $now->subDays(10),
        ]);

        $svc = app(TelemetryAnalyticsService::class);
        $chart = $svc->chartSeriesForMonitor(
            (int) $container->id,
            'temperature_c',
            $now->subHour(),
            $now,
            (int) $rental->id,
            (int) $user->id
        );

        $this->assertFalse($chart['used_extended_lookback']);
        $this->assertFalse($chart['telemetry_backed']);
        $this->assertSame(0, $chart['samples_in_range']);
    }

    /**
     * @return array{0: \App\Models\Container, 1: Rental, 2: User}
     */
    private function createScopedFixtures(): array
    {
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Lookbackland',
            'iso_code' => 'LB',
            'phone_code' => '+0',
            'interest_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner = Owner::query()->create([
            'name' => 'LB Owner',
            'email' => 'lb-owner@test.local',
            'phone_number' => '+1000000004',
        ]);
        $port = Port::query()->create([
            'name' => 'LB Port',
            'city' => 'Test',
            'country_id' => $countryId,
        ]);
        $container = Container::query()->create([
            'serial_number' => 'LB-1',
            'type' => 'standard',
            'width' => 2.44,
            'length' => 6.06,
            'height' => 2.59,
            'max_weight' => 10000,
            'manufacture_date' => now()->subYear(),
            'photo' => null,
            'iot_active' => true,
            'current_status' => 'available',
            'owner_id' => $owner->id,
            'current_port_id' => $port->id,
        ]);
        $user = User::factory()->create();
        $rental = Rental::query()->create([
            'user_id' => $user->id,
            'container_id' => $container->id,
            'route_id' => null,
            'origin_port_id' => $port->id,
            'destination_port_id' => $port->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'rental_days' => 30,
            'cargo_types' => ['general'],
            'status' => 'active',
            'payment_status' => 'paid',
            'price' => 100,
            'price_breakdown' => [],
        ]);

        return [$container, $rental, $user];
    }
}
