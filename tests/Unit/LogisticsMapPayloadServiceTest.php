<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LogisticsMapPayloadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogisticsMapPayloadServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function build_for_user_returns_same_top_level_keys_as_map_json_contract(): void
    {
        $user = User::factory()->create();
        $fixedNow = CarbonImmutable::parse('2026-05-02 14:00:00', 'UTC');

        $payload = app(LogisticsMapPayloadService::class)->buildForUser($user, $fixedNow);

        $this->assertSame([
            'ports',
            'route_edges',
            'vessel_positions',
            'positions',
            'rental_route_segments',
            'is_ops_view',
        ], array_keys($payload));

        // Mirror JsonResponse encoding: some values are Collections until serialized.
        $decoded = json_decode(json_encode($payload), true);
        $this->assertIsArray($decoded);
        $this->assertIsArray($decoded['ports']);
        $this->assertIsArray($decoded['route_edges']);
        $this->assertIsArray($decoded['vessel_positions']);
        $this->assertIsArray($decoded['positions']);
        $this->assertIsArray($decoded['rental_route_segments']);
        $this->assertFalse($decoded['is_ops_view']);
    }
}
