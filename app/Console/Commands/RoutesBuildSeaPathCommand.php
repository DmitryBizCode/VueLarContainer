<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Port;
use App\Models\Route;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RoutesBuildSeaPathCommand extends Command
{
    protected $signature = 'routes:build-sea-path
        {--only-missing : Only build paths for routes without sea_path (default)}
        {--force : Rebuild even if sea_path exists}
        {--route-id= : Build for a single route ID}
        {--limit= : Limit number of routes processed}
        {--timeout=10 : HTTP timeout in seconds}
        {--update-distance : Update routes.distance (km) from searoute length}
        {--update-days : Update routes.estimated_days using a distance→days heuristic}
        {--dry-run : Compute but do not write to DB}';

    protected $description = 'Build and store sea_path (sea lanes) for routes using searoute-js';

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $force = (bool) $this->option('force');
        $routeId = $this->option('route-id') !== null ? (int) $this->option('route-id') : null;
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $timeout = max(1, (int) $this->option('timeout'));
        $updateDistance = (bool) $this->option('update-distance');
        $updateDays = (bool) $this->option('update-days');
        $dryRun = (bool) $this->option('dry-run');

        if ($force) {
            $onlyMissing = false;
        }

        $baseUrl = rtrim((string) env('SEAROUTE_URL', 'http://searoute:3001'), '/');

        $query = Route::query()
            ->where('route_status', 'open')
            ->with(['originPort:id,latitude,longitude', 'destinationPort:id,latitude,longitude'])
            ->orderBy('id');

        if ($routeId !== null) {
            $query->where('id', $routeId);
        } elseif ($onlyMissing) {
            $query->where(function ($q) {
                $q->whereNull('sea_path');
            });
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Route> $routes */
        $routes = $query->get();
        if ($routes->isEmpty()) {
            $this->info('No matching routes found.');

            return self::SUCCESS;
        }

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($routes as $route) {
            /** @var Route $route */
            $o = $route->originPort;
            $d = $route->destinationPort;
            if (! $o instanceof Port || ! $d instanceof Port) {
                $this->warn("Route #{$route->id}: missing ports.");
                $failed++;

                continue;
            }
            if ($o->latitude === null || $o->longitude === null || $d->latitude === null || $d->longitude === null) {
                $this->warn("Route #{$route->id}: missing coordinates.");
                $failed++;

                continue;
            }

            if (! $force && is_array($route->sea_path) && $route->sea_path !== []) {
                $skipped++;

                continue;
            }

            try {
                /** @var Response $resp */
                $resp = Http::timeout($timeout)->post("{$baseUrl}/route", [
                    'origin' => ['lat' => (float) $o->latitude, 'lng' => (float) $o->longitude],
                    'destination' => ['lat' => (float) $d->latitude, 'lng' => (float) $d->longitude],
                    'units' => 'kilometers',
                    'drop_endpoints' => true,
                ]);

                if (! $resp->ok()) {
                    $this->warn("Route #{$route->id}: searoute HTTP {$resp->status()}");
                    $failed++;

                    continue;
                }

                $points = $resp->json('points');
                if (! is_array($points) || count($points) < 1) {
                    $this->warn("Route #{$route->id}: empty points.");
                    $failed++;

                    continue;
                }

                $lengthKm = $resp->json('length');
                $lengthKm = is_numeric($lengthKm) ? (float) $lengthKm : null;

                // Normalize to [lat,lng] pairs (floats)
                $waypoints = [];
                foreach ($points as $row) {
                    if (! is_array($row) || count($row) < 2) {
                        continue;
                    }
                    $lat = is_numeric($row[0] ?? null) ? (float) $row[0] : null;
                    $lng = is_numeric($row[1] ?? null) ? (float) $row[1] : null;
                    if ($lat === null || $lng === null) {
                        continue;
                    }
                    $waypoints[] = [$lat, $lng];
                }

                if ($waypoints === []) {
                    $this->warn("Route #{$route->id}: no valid waypoint pairs.");
                    $failed++;

                    continue;
                }

                if (! $dryRun) {
                    $route->sea_path = $waypoints;
                    if ($updateDistance && $lengthKm !== null && $lengthKm > 0) {
                        $route->distance = $lengthKm;
                    }
                    if ($updateDays) {
                        $km = $lengthKm ?? ((float) $route->distance);
                        $route->estimated_days = self::heuristicDaysForKm($km);
                    }
                    $route->save();
                }

                $ok++;
                $this->line("Route #{$route->id}: saved ".count($waypoints).' waypoint(s).');
            } catch (\Throwable $e) {
                $this->warn("Route #{$route->id}: error {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. ok={$ok}, skipped={$skipped}, failed={$failed}.");

        // Treat this as a best-effort batch: some pairs can be missing in the underlying network.
        // Returning SUCCESS keeps scripted runs stable; the command still prints failures.
        return self::SUCCESS;
    }

    private static function heuristicDaysForKm(float $km): int
    {
        $km = max(0.0, $km);
        if ($km <= 120.0) {
            return 1;
        }
        if ($km <= 300.0) {
            return 2;
        }
        if ($km <= 900.0) {
            return 3;
        }
        if ($km <= 2000.0) {
            return 6;
        }
        if ($km <= 4500.0) {
            return 12;
        }

        // Very long legs (intercontinental)
        return min(30, (int) max(14, ceil($km / 500.0)));
    }
}
