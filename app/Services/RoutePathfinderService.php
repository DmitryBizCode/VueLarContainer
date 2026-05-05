<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Route;

class RoutePathfinderService
{
    /**
     * Returns all port IDs reachable from $originPortId via open routes (single-source Dijkstra, no early exit).
     *
     * @return list<int>
     */
    public function reachablePortIds(int $originPortId): array
    {
        $routes = Route::query()
            ->where('route_status', 'open')
            ->get(['origin_port_id', 'destination_port_id', 'estimated_days']);

        $graph = [];
        foreach ($routes as $r) {
            $from = (int) $r->origin_port_id;
            $to = (int) $r->destination_port_id;
            $weight = (int) $r->estimated_days;
            if (! isset($graph[$from][$to]) || $weight < $graph[$from][$to]) {
                $graph[$from][$to] = $weight;
            }
        }

        if (empty($graph[$originPortId])) {
            return [];
        }

        $allPorts = array_unique(
            array_merge(array_keys($graph), ...array_map('array_keys', $graph))
        );

        $dist = [$originPortId => 0];
        foreach ($allPorts as $p) {
            if (! isset($dist[$p])) {
                $dist[$p] = PHP_INT_MAX;
            }
        }

        $visited = [];
        while (true) {
            $u = null;
            foreach ($dist as $port => $d) {
                if (isset($visited[$port])) {
                    continue;
                }
                if ($u === null || $d < $dist[$u]) {
                    $u = $port;
                }
            }
            if ($u === null || $dist[$u] === PHP_INT_MAX) {
                break;
            }
            $visited[$u] = true;

            foreach ($graph[$u] ?? [] as $v => $w) {
                if (! isset($visited[$v]) && $dist[$v] > $dist[$u] + $w) {
                    $dist[$v] = $dist[$u] + $w;
                }
            }
        }

        return array_values(array_filter(
            array_keys($dist),
            fn ($p) => $p !== $originPortId && $dist[$p] < PHP_INT_MAX,
        ));
    }

    /**
     * @return array{legs: list<array{route_id:int,origin_port_id:int,destination_port_id:int,estimated_days:int,distance:float}>, total_days:int, total_distance:float, multi_hop:bool}|null
     */
    public function findPath(int $originPortId, int $destinationPortId, string $metric = 'time'): ?array
    {
        if ($originPortId === $destinationPortId) {
            return [
                'legs' => [],
                'total_days' => 0,
                'total_distance' => 0.0,
                'multi_hop' => false,
            ];
        }

        $routes = Route::query()
            ->where('route_status', 'open')
            ->get(['id', 'origin_port_id', 'destination_port_id', 'estimated_days', 'distance']);

        /** @var array<int, array<int, array{route_id:int, estimated_days:int, distance:float, weight:float}>> $bestEdge */
        $bestEdge = [];
        foreach ($routes as $route) {
            $from = (int) $route->origin_port_id;
            $to = (int) $route->destination_port_id;
            $weight = $metric === 'cost'
                ? (float) $route->distance
                : (float) $route->estimated_days;
            if (! isset($bestEdge[$from][$to]) || $weight < $bestEdge[$from][$to]['weight']) {
                $bestEdge[$from][$to] = [
                    'route_id' => (int) $route->id,
                    'estimated_days' => (int) $route->estimated_days,
                    'distance' => (float) $route->distance,
                    'weight' => $weight,
                ];
            }
        }

        $vertices = [];
        foreach ($bestEdge as $from => $tos) {
            $vertices[$from] = true;
            foreach (array_keys($tos) as $to) {
                $vertices[(int) $to] = true;
            }
        }
        $vertices[$originPortId] = true;
        $vertices[$destinationPortId] = true;
        $vertexList = array_keys($vertices);

        $dist = [];
        $prev = [];
        foreach ($vertexList as $v) {
            $dist[$v] = INF;
            $prev[$v] = null;
        }
        $dist[$originPortId] = 0.0;

        $visited = [];
        $n = count($vertexList);
        for ($i = 0; $i < $n; $i++) {
            $u = null;
            $best = INF;
            foreach ($vertexList as $v) {
                if (($visited[$v] ?? false) || $dist[$v] === INF) {
                    continue;
                }
                if ($u === null || $dist[$v] < $best) {
                    $best = $dist[$v];
                    $u = $v;
                }
            }
            if ($u === null || $dist[$u] === INF) {
                break;
            }
            $visited[$u] = true;
            if ($u === $destinationPortId) {
                break;
            }
            foreach ($bestEdge[$u] ?? [] as $to => $edge) {
                if (($visited[$to] ?? false)) {
                    continue;
                }
                $alt = $dist[$u] + $edge['weight'];
                if ($alt < $dist[$to]) {
                    $dist[$to] = $alt;
                    $prev[$to] = [
                        'from' => $u,
                        'route_id' => $edge['route_id'],
                        'estimated_days' => $edge['estimated_days'],
                        'distance' => $edge['distance'],
                    ];
                }
            }
        }

        if ($dist[$destinationPortId] === INF) {
            return null;
        }

        $legsRev = [];
        $cur = $destinationPortId;
        while ($cur !== $originPortId) {
            $p = $prev[$cur];
            if ($p === null) {
                return null;
            }
            $from = (int) $p['from'];
            $legsRev[] = [
                'route_id' => (int) $p['route_id'],
                'origin_port_id' => $from,
                'destination_port_id' => $cur,
                'estimated_days' => (int) $p['estimated_days'],
                'distance' => (float) $p['distance'],
            ];
            $cur = $from;
        }
        $legs = array_reverse($legsRev);

        $totalDays = (int) array_sum(array_column($legs, 'estimated_days'));
        $totalDistance = (float) array_sum(array_column($legs, 'distance'));

        return [
            'legs' => $legs,
            'total_days' => $totalDays,
            'total_distance' => $totalDistance,
            'multi_hop' => count($legs) > 1,
        ];
    }
}
