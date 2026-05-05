<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\RouteSeeder;
use Database\Support\SeaPathWaypointPatches;
use Illuminate\Console\Command;

/**
 * Lists RouteSeeder europeDemoEdges port pairs that have no (non-empty) entry in
 * SeaPathWaypointPatches::searouteOverrideByPortPair() after merge.
 */
class RoutesAuditEuropeSeaPathPatchesCommand extends Command
{
    protected $signature = 'routes:audit-europe-sea-path-patches';

    protected $description = 'Report europe demo route legs missing canonical sea_path overrides';

    public function handle(): int
    {
        $map = SeaPathWaypointPatches::searouteOverrideByPortPair();
        $missing = [];

        foreach (RouteSeeder::europeDemoEdges() as [$from, $to, $_d, $_km]) {
            $key = $from.'|'.$to;
            if (! isset($map[$key]) || ! is_array($map[$key]) || $map[$key] === []) {
                $missing[] = $key;
            }
        }

        if ($missing === []) {
            $this->info('All europeDemoEdges() legs have canonical overrides.');

            return self::SUCCESS;
        }

        foreach ($missing as $line) {
            $this->line($line);
        }
        $this->warn('Missing count: '.count($missing));

        return self::FAILURE;
    }
}
