<?php

namespace Tests\Unit;

use App\Support\MetricsSampleAggregator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetricsSampleAggregatorTest extends TestCase
{
    #[Test]
    public function median_single_value(): void
    {
        $this->assertSame(3.5, MetricsSampleAggregator::medianOfFloats([3.5]));
    }

    #[Test]
    public function median_odd_count(): void
    {
        $this->assertSame(5.0, MetricsSampleAggregator::medianOfFloats([1, 5, 9]));
    }

    #[Test]
    public function median_even_count_averages_middle_pair(): void
    {
        $this->assertSame(3.5, MetricsSampleAggregator::medianOfFloats([1, 2, 5, 6]));
    }

    #[Test]
    public function median_ignores_non_numeric(): void
    {
        $this->assertSame(2.0, MetricsSampleAggregator::medianOfFloats([1, 'x', 3]));
    }

    #[Test]
    public function median_empty_is_zero(): void
    {
        $this->assertSame(0.0, MetricsSampleAggregator::medianOfFloats([]));
    }

    #[Test]
    public function aggregate_merges_same_sensor_and_computes_median(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'temperature_c',
                'value' => 4.0,
                'unit' => '°C',
                'recorded_at' => '2026-04-05 11:59:00',
            ],
            [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'temperature_c',
                'value' => 6.0,
                'unit' => '°C',
                'recorded_at' => '2026-04-05 11:59:10',
            ],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1, $out[0]['container_id']);
        $this->assertSame(10, $out[0]['rental_id']);
        $this->assertSame('temperature_c', $out[0]['type']);
        $this->assertSame(5.0, $out[0]['value']);
        $meta = json_decode((string) $out[0]['meta'], true);
        $this->assertTrue($meta['aggregated']);
        $this->assertSame('median', $meta['aggregation']);
        $this->assertSame(2, $meta['sample_count']);
    }

    #[Test]
    public function aggregate_binary_door_open_uses_max_not_median(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'door_open',
                'value' => 0.0,
                'unit' => null,
                'recorded_at' => '2026-04-05 11:59:00',
            ],
            [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'door_open',
                'value' => 0.0,
                'unit' => null,
                'recorded_at' => '2026-04-05 11:59:30',
            ],
            [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'door_open',
                'value' => 1.0,
                'unit' => null,
                'recorded_at' => '2026-04-05 11:59:45',
            ],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
        $meta = json_decode((string) $out[0]['meta'], true);
        $this->assertSame('any_on', $meta['aggregation']);
    }

    #[Test]
    public function aggregate_binary_pump_running_uses_max_when_majority_is_zero(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [];
        for ($i = 0; $i < 5; $i++) {
            $raw[] = [
                'container_id' => 1,
                'rental_id' => 10,
                'type' => 'pump_running',
                'value' => 0.0,
                'unit' => null,
                'recorded_at' => '2026-04-05 11:59:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ];
        }
        $raw[] = [
            'container_id' => 1,
            'rental_id' => 10,
            'type' => 'pump_running',
            'value' => 1.0,
            'unit' => null,
            'recorded_at' => '2026-04-05 11:59:50',
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
        $meta = json_decode((string) $out[0]['meta'], true);
        $this->assertSame('any_on', $meta['aggregation']);
    }

    #[Test]
    public function aggregate_binary_type_name_is_case_insensitive(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'PUMP_RUNNING', 'value' => 0, 'recorded_at' => '2026-04-05 11:59:00'],
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'PUMP_RUNNING', 'value' => 1, 'recorded_at' => '2026-04-05 11:59:30'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
        $this->assertSame('PUMP_RUNNING', $out[0]['type']);
    }

    #[Test]
    public function aggregate_discrete_suffix_on_matches_pattern(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'aux_relay_on', 'value' => 0, 'recorded_at' => '2026-04-05 11:59:00'],
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'aux_relay_on', 'value' => 1, 'recorded_at' => '2026-04-05 11:59:30'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
        $meta = json_decode((string) $out[0]['meta'], true);
        $this->assertSame('any_on', $meta['aggregation']);
    }

    #[Test]
    public function aggregate_binary_pump_running_accepts_json_boolean_true(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'pump_running', 'value' => false, 'recorded_at' => '2026-04-05 11:59:00'],
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'pump_running', 'value' => true, 'recorded_at' => '2026-04-05 11:59:30'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
    }

    #[Test]
    public function aggregate_binary_pump_running_still_uses_max_when_config_list_empty(): void
    {
        config(['metrics_buffer.binary_sensor_types' => []]);

        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'pump_running', 'value' => 0, 'recorded_at' => '2026-04-05 11:59:00'],
            ['container_id' => 1, 'rental_id' => 10, 'type' => 'pump_running', 'value' => 1, 'recorded_at' => '2026-04-05 11:59:30'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(1.0, $out[0]['value']);
    }

    #[Test]
    public function aggregate_binary_all_zero_stays_zero(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => null, 'type' => 'ventilation_on', 'value' => 0, 'recorded_at' => '2026-04-05 11:59:00'],
            ['container_id' => 1, 'rental_id' => null, 'type' => 'ventilation_on', 'value' => 0, 'recorded_at' => '2026-04-05 11:59:10'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(1, $out);
        $this->assertSame(0.0, $out[0]['value']);
    }

    #[Test]
    public function aggregate_groups_null_rental_separately(): void
    {
        $at = CarbonImmutable::parse('2026-04-05 12:00:00');
        $raw = [
            ['container_id' => 1, 'rental_id' => null, 'type' => 'x', 'value' => 1, 'recorded_at' => '2026-04-05 11:00:00'],
            ['container_id' => 1, 'rental_id' => 2, 'type' => 'x', 'value' => 3, 'recorded_at' => '2026-04-05 11:00:00'],
        ];

        $out = MetricsSampleAggregator::aggregateToMedianRows($raw, $at);
        $this->assertCount(2, $out);
    }
}
