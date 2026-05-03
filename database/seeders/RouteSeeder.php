<?php

namespace Database\Seeders;

use App\Models\Port;
use App\Models\Route;
use Database\Support\SeaPathWaypointPatches;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Open shipping legs (approximate days / distance for pathfinder demos).
     *
     * @return list<array{0: string, 1: string, 2: int, 3: float}>
     */
    private function routeEdges(): array
    {
        return [
            ['Port of Hamburg', 'Port of Rotterdam', 2, 430.0],
            ['Port of Rotterdam', 'Port of Hamburg', 2, 430.0],
            ['Port of Hamburg', 'Port of Bremerhaven', 1, 120.0],
            ['Port of Bremerhaven', 'Port of Hamburg', 1, 120.0],
            ['Port of Wilhelmshaven', 'Port of Bremerhaven', 1, 90.0],
            ['Port of Bremerhaven', 'Port of Wilhelmshaven', 1, 90.0],
            ['Port of Wilhelmshaven', 'Port of Rotterdam', 1, 340.0],
            ['Port of Rotterdam', 'Port of Wilhelmshaven', 1, 340.0],
            ['Port of Kiel', 'Port of Hamburg', 1, 95.0],
            ['Port of Hamburg', 'Port of Kiel', 1, 95.0],
            ['Port of Rotterdam', 'Port of Antwerp', 1, 190.0],
            ['Port of Antwerp', 'Port of Rotterdam', 1, 190.0],
            ['Port of Rotterdam', 'Port of Amsterdam', 1, 95.0],
            ['Port of Amsterdam', 'Port of Rotterdam', 1, 95.0],
            ['Port of Bordeaux', 'Port of Le Havre', 3, 1050.0],
            ['Port of Le Havre', 'Port of Bordeaux', 3, 1050.0],
            ['Port of Le Havre', 'Port of Southampton', 2, 320.0],
            ['Port of Southampton', 'Port of Le Havre', 2, 320.0],
            ['Port of Hamburg', 'Port of Gdansk', 2, 780.0],
            ['Port of Gdansk', 'Port of Hamburg', 2, 780.0],
            ['Port of Copenhagen', 'Port of Aarhus', 1, 170.0],
            ['Port of Aarhus', 'Port of Copenhagen', 1, 170.0],
            ['Port of Copenhagen', 'Port of Gothenburg', 1, 320.0],
            ['Port of Gothenburg', 'Port of Copenhagen', 1, 320.0],
            ['Port of Oslo', 'Port of Gothenburg', 1, 290.0],
            ['Port of Gothenburg', 'Port of Oslo', 1, 290.0],
            ['Port of Gothenburg', 'Port of Helsinki', 2, 890.0],
            ['Port of Helsinki', 'Port of Gothenburg', 2, 890.0],
            ['Port of Stockholm', 'Port of Helsinki', 1, 400.0],
            ['Port of Helsinki', 'Port of Stockholm', 1, 400.0],
            ['Port of Stockholm', 'Port of Tallinn', 1, 380.0],
            ['Port of Tallinn', 'Port of Stockholm', 1, 380.0],
            ['Port of Oslo', 'Port of Copenhagen', 2, 500.0],
            ['Port of Copenhagen', 'Port of Oslo', 2, 500.0],
            ['Port of Helsinki', 'Port of Tallinn', 1, 95.0],
            ['Port of Tallinn', 'Port of Helsinki', 1, 95.0],
            ['Port of Tallinn', 'Port of Riga', 1, 310.0],
            ['Port of Riga', 'Port of Tallinn', 1, 310.0],
            ['Port of Rotterdam', 'Port of Le Havre', 3, 620.0],
            ['Port of Le Havre', 'Port of Rotterdam', 3, 620.0],
            ['Port of Le Havre', 'Port of Marseille', 2, 880.0],
            ['Port of Marseille', 'Port of Le Havre', 2, 880.0],
            ['Port of Marseille', 'Port of Barcelona', 1, 520.0],
            ['Port of Barcelona', 'Port of Marseille', 1, 520.0],
            ['Port of Barcelona', 'Port of Valencia', 1, 380.0],
            ['Port of Valencia', 'Port of Barcelona', 1, 380.0],
            ['Port of Valencia', 'Port of Bilbao', 2, 620.0],
            ['Port of Bilbao', 'Port of Valencia', 2, 620.0],
            ['Port of Valencia', 'Port of Algeciras', 2, 650.0],
            ['Port of Algeciras', 'Port of Valencia', 2, 650.0],
            ['Port of Vigo', 'Port of Leixoes', 1, 140.0],
            ['Port of Leixoes', 'Port of Vigo', 1, 140.0],
            ['Port of Vigo', 'Port of Bilbao', 1, 520.0],
            ['Port of Bilbao', 'Port of Vigo', 1, 520.0],
            ['Port of Algeciras', 'Port of Sines', 1, 470.0],
            ['Port of Sines', 'Port of Algeciras', 1, 470.0],
            ['Port of Sines', 'Port of Le Havre', 4, 1450.0],
            ['Port of Le Havre', 'Port of Sines', 4, 1450.0],
            ['Port of Lisbon', 'Port of Sines', 1, 140.0],
            ['Port of Sines', 'Port of Lisbon', 1, 140.0],
            ['Port of Lisbon', 'Port of Le Havre', 4, 1500.0],
            ['Port of Le Havre', 'Port of Lisbon', 4, 1500.0],
            ['Port of Leixoes', 'Port of Lisbon', 1, 330.0],
            ['Port of Lisbon', 'Port of Leixoes', 1, 330.0],
            ['Port of Leixoes', 'Port of Le Havre', 4, 1650.0],
            ['Port of Le Havre', 'Port of Leixoes', 4, 1650.0],
            ['Port of Barcelona', 'Port of Genoa', 2, 720.0],
            ['Port of Genoa', 'Port of Barcelona', 2, 720.0],
            ['Port of Genoa', 'Port of Koper', 1, 520.0],
            ['Port of Koper', 'Port of Genoa', 1, 520.0],
            ['Port of Trieste', 'Port of Koper', 1, 70.0],
            ['Port of Koper', 'Port of Trieste', 1, 70.0],
            ['Port of La Spezia', 'Port of Genoa', 1, 120.0],
            ['Port of Genoa', 'Port of La Spezia', 1, 120.0],
            ['Port of Livorno', 'Port of Genoa', 1, 220.0],
            ['Port of Genoa', 'Port of Livorno', 1, 220.0],
            ['Port of Gioia Tauro', 'Port of Genoa', 4, 1200.0],
            ['Port of Genoa', 'Port of Gioia Tauro', 4, 1200.0],
            ['Port of Gioia Tauro', 'Port of Port Said', 6, 1600.0],
            ['Port of Port Said', 'Port of Gioia Tauro', 6, 1600.0],
            ['Port of Venice', 'Port of Trieste', 1, 160.0],
            ['Port of Trieste', 'Port of Venice', 1, 160.0],
            ['Port of Ravenna', 'Port of Venice', 1, 160.0],
            ['Port of Venice', 'Port of Ravenna', 1, 160.0],
            ['Port of Naples', 'Port of Gioia Tauro', 2, 420.0],
            ['Port of Gioia Tauro', 'Port of Naples', 2, 420.0],
            ['Port of Taranto', 'Port of Gioia Tauro', 2, 520.0],
            ['Port of Gioia Tauro', 'Port of Taranto', 2, 520.0],
            ['Port of Koper', 'Port of Piraeus', 3, 1450.0],
            ['Port of Piraeus', 'Port of Koper', 3, 1450.0],
            ['Port of Piraeus', 'Port of Istanbul Ambarli', 2, 420.0],
            ['Port of Istanbul Ambarli', 'Port of Piraeus', 2, 420.0],
            ['Port of Istanbul Ambarli', 'Port of Constanta', 3, 640.0],
            ['Port of Constanta', 'Port of Istanbul Ambarli', 3, 640.0],
            ['Port of Genoa', 'Port of Marseille', 1, 480.0],
            ['Port of Marseille', 'Port of Genoa', 1, 480.0],
            ['Port of Odesa', 'Port of Constanta', 1, 340.0],
            ['Port of Constanta', 'Port of Odesa', 1, 340.0],
            ['Port of Constanta', 'Port of Piraeus', 3, 980.0],
            ['Port of Piraeus', 'Port of Constanta', 3, 980.0],
            ['Port of Odesa', 'Port of Istanbul Ambarli', 2, 650.0],
            ['Port of Istanbul Ambarli', 'Port of Odesa', 2, 650.0],
            ['Port of Odesa', 'Port of Rotterdam', 6, 2400.0],
            ['Port of Rotterdam', 'Port of Odesa', 6, 2400.0],
            ['Port of Odesa', 'Port of Hamburg', 5, 2100.0],
            ['Port of Hamburg', 'Port of Odesa', 5, 2100.0],
            ['Port of Gdansk', 'Port of Rotterdam', 3, 1180.0],
            ['Port of Rotterdam', 'Port of Gdansk', 3, 1180.0],
            ['Port of Gdynia', 'Port of Gdansk', 1, 35.0],
            ['Port of Gdansk', 'Port of Gdynia', 1, 35.0],
            ['Port of Valencia', 'Port of Genoa', 3, 1100.0],
            ['Port of Genoa', 'Port of Valencia', 3, 1100.0],
            ['Port of Antwerp', 'Port of Hamburg', 2, 550.0],
            ['Port of Hamburg', 'Port of Antwerp', 2, 550.0],
            ['Port of Zeebrugge', 'Port of Rotterdam', 1, 160.0],
            ['Port of Rotterdam', 'Port of Zeebrugge', 1, 160.0],
            ['Port of Rotterdam', 'Port of New York', 22, 5850.0],
            ['Port of New York', 'Port of Rotterdam', 22, 5850.0],
            ['Port of Los Angeles', 'Port of Yokohama', 14, 8900.0],
            ['Port of Yokohama', 'Port of Los Angeles', 14, 8900.0],
            ['Port of Singapore', 'Port of Shanghai', 5, 2800.0],
            ['Port of Shanghai', 'Port of Singapore', 5, 2800.0],
            ['Port of Jebel Ali', 'Port of Singapore', 7, 5900.0],
            ['Port of Singapore', 'Port of Jebel Ali', 7, 5900.0],
            ['Port of Piraeus', 'Port of Jebel Ali', 8, 3300.0],
            ['Port of Jebel Ali', 'Port of Piraeus', 8, 3300.0],
            ['Port of Rotterdam', 'Port of Singapore', 28, 10600.0],
            ['Port of Singapore', 'Port of Rotterdam', 28, 10600.0],
            ['Port of Barcelona', 'Port of New York', 18, 6600.0],
            ['Port of New York', 'Port of Barcelona', 18, 6600.0],
            ['Port of Piraeus', 'Port of Port Said', 5, 1200.0],
            ['Port of Port Said', 'Port of Piraeus', 5, 1200.0],
            ['Port of Port Said', 'Port of Jebel Ali', 9, 4400.0],
            ['Port of Jebel Ali', 'Port of Port Said', 9, 4400.0],
            ['Port of Alexandria', 'Port of Port Said', 1, 220.0],
            ['Port of Port Said', 'Port of Alexandria', 1, 220.0],
            ['Port of Damietta', 'Port of Port Said', 1, 120.0],
            ['Port of Port Said', 'Port of Damietta', 1, 120.0],
            ['Khalifa Port', 'Port of Jebel Ali', 1, 140.0],
            ['Port of Jebel Ali', 'Khalifa Port', 1, 140.0],
            ['Port of Haifa', 'Port of Port Said', 2, 520.0],
            ['Port of Port Said', 'Port of Haifa', 2, 520.0],
            ['Port of Haifa', 'Port of Piraeus', 4, 1500.0],
            ['Port of Piraeus', 'Port of Haifa', 4, 1500.0],
            ['Malta Freeport', 'Port of Gioia Tauro', 2, 780.0],
            ['Port of Gioia Tauro', 'Malta Freeport', 2, 780.0],
            ['Port of Limassol', 'Port of Port Said', 2, 500.0],
            ['Port of Port Said', 'Port of Limassol', 2, 500.0],
            ['Port of Thessaloniki', 'Port of Piraeus', 1, 520.0],
            ['Port of Piraeus', 'Port of Thessaloniki', 1, 520.0],
            ['Port of Thessaloniki', 'Port of Istanbul Ambarli', 2, 560.0],
            ['Port of Istanbul Ambarli', 'Port of Thessaloniki', 2, 560.0],
            ['Port of Thessaloniki', 'Port of Constanta', 3, 1050.0],
            ['Port of Constanta', 'Port of Thessaloniki', 3, 1050.0],
            ['Tanger Med', 'Port of Algeciras', 1, 80.0],
            ['Port of Algeciras', 'Tanger Med', 1, 80.0],
            ['Port of Casablanca', 'Tanger Med', 1, 340.0],
            ['Tanger Med', 'Port of Casablanca', 1, 340.0],
            ['Port of Algiers', 'Port of Marseille', 2, 780.0],
            ['Port of Marseille', 'Port of Algiers', 2, 780.0],
            ['Port of Rades', 'Port of Marseille', 2, 950.0],
            ['Port of Marseille', 'Port of Rades', 2, 950.0],
        ];
    }

    public function run(): void
    {
        $portIds = Port::query()->pluck('id', 'name');
        $now = now();

        foreach ($this->routeEdges() as [$fromName, $toName, $days, $distance]) {
            $originId = $portIds[$fromName] ?? null;
            $destId = $portIds[$toName] ?? null;
            if (! $originId || ! $destId || $originId === $destId) {
                continue;
            }

            $exists = Route::query()
                ->where('origin_port_id', $originId)
                ->where('destination_port_id', $destId)
                ->exists();

            if ($exists) {
                continue;
            }

            Route::query()->create([
                'origin_port_id' => $originId,
                'destination_port_id' => $destId,
                'estimated_days' => $days,
                'distance' => $distance,
                'route_status' => 'open',
            ]);
        }

        $this->applyRegionalSeaPaths($now);
        $this->applyEarlySeaPathPatches($now);
        $this->applySymmetricSeaPathBackfill($now);
    }

    /**
     * Fills sea_path for known legs when still NULL (was 2026_05_01_100000 migration).
     */
    private function applyEarlySeaPathPatches(\DateTimeInterface $now): void
    {
        $patches = SeaPathWaypointPatches::earlyPatchLegs();
        $names = [];
        foreach ($patches as [$from, $to, $_waypoints]) {
            $names[$from] = true;
            $names[$to] = true;
        }

        $portIds = Port::query()->whereIn('name', array_keys($names))->pluck('id', 'name');

        foreach ($patches as [$from, $to, $waypoints]) {
            $fromId = $portIds[$from] ?? null;
            $toId = $portIds[$to] ?? null;
            if (! $fromId || ! $toId) {
                continue;
            }
            Route::query()
                ->where('origin_port_id', $fromId)
                ->where('destination_port_id', $toId)
                ->whereNull('sea_path')
                ->update(['sea_path' => $waypoints, 'updated_at' => $now]);
        }
    }

    /**
     * Symmetric backfill for allowlisted port pairs (was 2026_05_01_110000 migration).
     *
     * @param  list<array{0:float,1:float}>  $waypoints
     */
    private function fillSeaPathIfNull(int $originId, int $destinationId, array $waypoints, \DateTimeInterface $now): void
    {
        Route::query()
            ->where('origin_port_id', $originId)
            ->where('destination_port_id', $destinationId)
            ->whereNull('sea_path')
            ->update(['sea_path' => array_values($waypoints), 'updated_at' => $now]);
    }

    private function applySymmetricSeaPathBackfill(\DateTimeInterface $now): void
    {
        $symmetricPatches = SeaPathWaypointPatches::symmetricBackfillLegs();
        $names = [];
        foreach ($symmetricPatches as [$from, $to, $_waypoints]) {
            $names[$from] = true;
            $names[$to] = true;
        }

        $portIds = Port::query()->whereIn('name', array_keys($names))->pluck('id', 'name');

        foreach ($symmetricPatches as [$from, $to, $waypoints]) {
            $fromId = $portIds[$from] ?? null;
            $toId = $portIds[$to] ?? null;
            if (! $fromId || ! $toId) {
                continue;
            }

            $this->fillSeaPathIfNull($fromId, $toId, $waypoints, $now);
            $this->fillSeaPathIfNull($toId, $fromId, array_reverse($waypoints), $now);
        }
    }

    /**
     * Short North Sea / Channel legs: bend polylines seaward (map display + vessel track).
     */
    private function applyRegionalSeaPaths(\DateTimeInterface $now): void
    {
        foreach (SeaPathWaypointPatches::routeSeederRegionalLegs() as [$from, $to, $waypoints]) {
            $fromId = Port::query()->where('name', $from)->value('id');
            $toId = Port::query()->where('name', $to)->value('id');
            if (! $fromId || ! $toId) {
                continue;
            }
            Route::query()
                ->where('origin_port_id', $fromId)
                ->where('destination_port_id', $toId)
                ->update([
                    'sea_path' => $waypoints,
                    'updated_at' => $now,
                ]);
        }
    }
}
