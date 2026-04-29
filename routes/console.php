<?php

use App\Models\Port;
use App\Services\RoutePathfinderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('routes:debug-path {--from=} {--to=} {--metric=cost}', function (RoutePathfinderService $pathfinder) {
    $fromName = (string) ($this->option('from') ?? '');
    $toName = (string) ($this->option('to') ?? '');
    $metric = strtolower((string) ($this->option('metric') ?? 'cost'));
    $metric = in_array($metric, ['cost', 'time'], true) ? $metric : 'cost';

    if ($fromName === '' || $toName === '') {
        $this->error('Provide --from and --to (exact port names).');

        return 2;
    }

    $from = Port::query()->where('name', $fromName)->first();
    $to = Port::query()->where('name', $toName)->first();
    if ($from === null || $to === null) {
        $this->error('Port not found by name.');

        return 1;
    }

    $path = $pathfinder->findPath((int) $from->id, (int) $to->id, $metric);
    if ($path === null) {
        $this->warn('No path found.');

        return 1;
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

    return 0;
})->purpose('Debug Dijkstra multi-hop routing');
