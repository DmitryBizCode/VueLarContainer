<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Port;
use App\Services\RoutePathfinderService;
use Illuminate\Console\Command;

class RoutesDebugPathCommand extends Command
{
    protected $signature = 'routes:debug-path
        {--from= : Origin port name (exact) }
        {--to= : Destination port name (exact) }
        {--metric=cost : cost|time (cost=distance, time=estimated_days)}';

    protected $description = 'Debug multi-hop route path (Dijkstra) between ports';

    public function handle(RoutePathfinderService $pathfinder): int
    {
        $fromName = (string) ($this->option('from') ?? '');
        $toName = (string) ($this->option('to') ?? '');
        $metric = strtolower((string) ($this->option('metric') ?? 'cost'));
        $metric = in_array($metric, ['cost', 'time'], true) ? $metric : 'cost';

        if ($fromName === '' || $toName === '') {
            $this->error('Provide --from and --to (port names).');

            return self::INVALID;
        }

        $from = Port::query()->where('name', $fromName)->first();
        $to = Port::query()->where('name', $toName)->first();
        if ($from === null || $to === null) {
            $this->error('Port not found by name.');

            return self::FAILURE;
        }

        $path = $pathfinder->findPath((int) $from->id, (int) $to->id, $metric);
        if ($path === null) {
            $this->warn('No path found.');

            return self::FAILURE;
        }

        $this->info('Path found: '.($path['multi_hop'] ? 'multi-hop' : 'direct'));
        $this->line('Total distance (km): '.number_format((float) $path['total_distance'], 0));
        $this->line('Total days: '.(int) $path['total_days']);
        $this->newLine();
        foreach ($path['legs'] as $i => $leg) {
            $this->line(sprintf(
                '%02d) route_id=%d %d -> %d | %d days | %.0f km',
                $i + 1,
                (int) $leg['route_id'],
                (int) $leg['origin_port_id'],
                (int) $leg['destination_port_id'],
                (int) $leg['estimated_days'],
                (float) $leg['distance'],
            ));
        }

        return self::SUCCESS;
    }
}
