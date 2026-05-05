<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CountrySeeder;
use Database\Seeders\PortSeeder;
use Database\Seeders\RouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutesBuildSeaPathCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_europe_command_reports_full_patch_coverage(): void
    {
        $this->assertSame(0, Artisan::call('routes:audit-europe-sea-path-patches'));
    }

    public function test_build_sea_path_skips_http_when_canonical_override_exists(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'No route found'], 404),
        ]);

        $this->seed(CountrySeeder::class);
        $this->seed(PortSeeder::class);
        $this->seed(RouteSeeder::class);

        $routeId = \App\Models\Route::query()
            ->whereHas('originPort', fn ($q) => $q->where('name', 'Port of Colombo'))
            ->whereHas('destinationPort', fn ($q) => $q->where('name', 'Port of Singapore'))
            ->value('id');

        $this->assertNotNull($routeId);

        $this->assertSame(0, Artisan::call('routes:build-sea-path', [
            '--force' => true,
            '--route-id' => (string) $routeId,
        ]));
        Http::assertNothingSent();

        $raw = \Illuminate\Support\Facades\DB::table('routes')->where('id', $routeId)->value('sea_path');
        $this->assertNotNull($raw);
        $decoded = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : $raw;
        $this->assertIsArray($decoded);
        $this->assertGreaterThanOrEqual(3, count($decoded));
    }
}
