<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures every major open shipping route between allowlisted ports has a maritime sea_path
 * so the map never falls back to land-crossing polylines.
 *
 * Waypoints are hand-picked coastal/open-sea anchors. Each entry is applied symmetrically
 * (A→B uses the list, B→A uses its reverse) and only where sea_path IS NULL.
 */
return new class extends Migration
{
    /**
     * @return list<array{0:string,1:string,2:list<array{0:float,1:float}>}>
     */
    private function symmetricPatches(): array
    {
        return [
            // --- Benelux / Channel / North Sea short legs ---
            ['Port of Rotterdam', 'Port of Antwerp', [[51.80, 3.50], [51.45, 3.55]]],
            ['Port of Antwerp', 'Port of Zeebrugge', [[51.35, 3.50], [51.32, 3.20]]],
            ['Port of Rotterdam', 'Port of Amsterdam', [[52.10, 4.10], [52.40, 4.35]]],
            ['Port of Antwerp', 'Port of Dunkirk', [[51.35, 2.95], [51.10, 2.35]]],
            ['Port of Dunkirk', 'Port of Le Havre', [[50.60, 1.40], [49.90, 0.45]]],
            ['Port of Le Havre', 'Port of Southampton', [[49.65, -0.50], [50.50, -1.20]]],
            ['Port of Southampton', 'Port of Felixstowe', [[50.90, -0.80], [51.40, 1.20]]],
            ['Port of Zeebrugge', 'Port of Rotterdam', [[51.50, 3.30], [51.80, 3.80]]],
            ['Port of Hamburg', 'Port of Bremerhaven', [[53.85, 8.45]]],
            ['Port of Bremerhaven', 'Port of Wilhelmshaven', [[53.75, 8.10]]],
            ['Port of Wilhelmshaven', 'Port of Rotterdam', [[53.50, 6.50], [52.80, 4.40]]],
            ['Port of Hamburg', 'Port of Kiel', [[53.90, 10.20], [54.35, 10.15]]],
            ['Port of Kiel', 'Port of Aarhus', [[54.85, 10.55], [56.00, 10.40]]],
            ['Port of Rotterdam', 'Port of Felixstowe', [[52.55, 3.35], [51.95, 2.05]]],
            ['Port of London', 'Port of Felixstowe', [[51.55, 1.35], [51.90, 1.55]]],
            ['Port of Rotterdam', 'Port of Hamburg', [[53.65, 6.05], [54.18, 7.85]]],
            ['Port of Antwerp', 'Port of Hamburg', [[51.80, 3.20], [53.40, 5.50], [54.00, 8.00]]],

            // --- Atlantic Europe ---
            ['Port of Rotterdam', 'Port of Le Havre', [[51.00, 1.50], [50.00, -0.20]]],
            ['Port of Felixstowe', 'Port of Dublin', [[51.50, 1.10], [51.00, -4.00], [52.80, -5.50]]],
            ['Port of Dublin', 'Port of Cork', [[52.80, -6.30], [51.70, -8.30]]],
            ['Port of Bordeaux', 'Port of Le Havre', [[47.50, -1.80]]],
            ['Port of Le Havre', 'Port of Lisbon', [[47.80, -4.60], [44.50, -8.00], [39.20, -11.80]]],
            ['Port of Le Havre', 'Port of Leixoes', [[48.00, -4.50], [46.50, -7.20], [43.80, -10.50]]],
            ['Port of Le Havre', 'Port of Sines', [[47.20, -4.80], [44.80, -8.50], [40.20, -11.50]]],

            // --- Iberia / Bay of Biscay / Portugal ---
            ['Port of Vigo', 'Port of Leixoes', [[41.28, -8.85], [41.15, -8.78]]],
            ['Port of Vigo', 'Port of Bilbao', [[42.42, -7.85], [43.15, -6.05], [43.85, -4.15]]],
            ['Port of Valencia', 'Port of Bilbao', [[40.15, -1.35], [41.55, -2.65], [42.75, -3.15]]],
            ['Port of Valencia', 'Port of Algeciras', [[38.35, -0.55], [37.10, -2.20], [36.45, -4.20], [36.18, -5.35]]],
            ['Port of Algeciras', 'Port of Sines', [[36.22, -6.85], [37.15, -8.55], [37.88, -8.92]]],
            ['Port of Lisbon', 'Port of Sines', [[38.60, -9.35], [38.10, -9.00]]],
            ['Port of Leixoes', 'Port of Lisbon', [[40.80, -9.30], [39.40, -9.40]]],

            // --- Western Mediterranean ---
            ['Port of Barcelona', 'Port of Valencia', [[40.80, 1.60], [39.80, 0.20]]],
            ['Port of Barcelona', 'Port of Marseille', [[41.05, 2.85], [42.10, 4.25], [42.85, 5.05]]],
            ['Port of Marseille', 'Port of Genoa', [[43.20, 6.30], [43.80, 8.20]]],
            ['Port of Genoa', 'Port of Livorno', [[43.90, 9.40], [43.60, 10.00]]],
            ['Port of Genoa', 'Port of La Spezia', [[44.10, 9.55]]],
            ['Port of Valencia', 'Port of Genoa', [[39.80, 2.80], [40.90, 5.80], [42.80, 8.60]]],
            ['Port of Le Havre', 'Port of Marseille', [
                [48.20, -4.85], [46.50, -6.80], [44.50, -8.50], [43.00, -9.30],
                [40.50, -9.50], [37.95, -8.90], [36.40, -6.20], [36.00, -4.00],
                [36.50, -0.50], [38.50, 1.00], [40.50, 3.00], [42.80, 5.00],
            ]],

            // --- Gibraltar / North Africa ---
            ['Port of Algeciras', 'Tanger Med', [[35.95, -5.40]]],
            ['Tanger Med', 'Port of Casablanca', [[35.30, -6.20], [33.70, -7.70]]],
            ['Port of Marseille', 'Port of Algiers', [[42.00, 4.80], [38.50, 3.80], [36.90, 3.20]]],
            ['Port of Marseille', 'Port of Rades', [[42.00, 5.50], [38.50, 8.00], [36.80, 10.30]]],

            // --- Central / Eastern Med ---
            ['Port of Genoa', 'Port of Koper', [[43.80, 9.80], [43.00, 12.40], [44.80, 13.20], [45.50, 13.60]]],
            ['Port of Koper', 'Port of Trieste', [[45.55, 13.70]]],
            ['Port of Trieste', 'Port of Venice', [[45.70, 13.30], [45.50, 12.70]]],
            ['Port of Venice', 'Port of Ravenna', [[45.20, 12.60], [44.50, 12.40]]],
            ['Port of Genoa', 'Port of Gioia Tauro', [[43.10, 9.80], [40.80, 11.80], [38.40, 15.40]]],
            ['Port of Gioia Tauro', 'Port of Naples', [[39.50, 15.40], [40.60, 14.30]]],
            ['Port of Gioia Tauro', 'Port of Taranto', [[38.80, 16.30], [40.20, 17.20]]],
            ['Malta Freeport', 'Port of Gioia Tauro', [[36.20, 15.20], [37.60, 15.70], [38.40, 15.80]]],

            // --- Adriatic / Aegean / Black Sea ---
            ['Port of Koper', 'Port of Piraeus', [[44.60, 13.40], [42.00, 17.50], [39.50, 21.00], [37.80, 23.40]]],
            ['Port of Piraeus', 'Port of Istanbul Ambarli', [[37.80, 24.50], [40.20, 26.40], [40.95, 28.70]]],
            ['Port of Istanbul Ambarli', 'Port of Constanta', [[41.70, 29.10], [43.50, 29.60], [44.10, 28.80]]],
            ['Port of Constanta', 'Port of Odesa', [[44.80, 30.50], [46.10, 30.80]]],
            ['Port of Piraeus', 'Port of Constanta', [[38.80, 24.80], [40.80, 25.80], [42.00, 28.50], [44.00, 28.90]]],
            ['Port of Piraeus', 'Port of Thessaloniki', [[38.60, 24.20], [40.00, 23.50]]],
            ['Port of Thessaloniki', 'Port of Istanbul Ambarli', [[40.40, 24.50], [40.70, 27.50]]],
            ['Port of Thessaloniki', 'Port of Constanta', [[40.80, 24.40], [42.00, 27.80], [43.80, 29.00], [44.00, 28.80]]],
            ['Port of Odesa', 'Port of Istanbul Ambarli', [[45.50, 30.50], [43.50, 32.00], [41.70, 29.50]]],

            // --- Long Med ↔ North Europe ---
            ['Port of Odesa', 'Port of Rotterdam', [
                [45.20, 30.80], [41.60, 29.00], [38.60, 24.80], [37.20, 20.00], [36.50, 11.80],
                [36.00, 4.00], [36.00, -1.50], [36.00, -6.00], [38.00, -9.40], [43.00, -9.50],
                [47.50, -4.80], [50.40, 0.40], [51.85, 3.90],
            ]],
            ['Port of Odesa', 'Port of Hamburg', [
                [45.20, 30.80], [41.60, 29.00], [38.60, 24.80], [37.20, 20.00], [36.50, 11.80],
                [36.00, 4.00], [36.00, -1.50], [36.00, -6.00], [38.00, -9.40], [43.00, -9.50],
                [47.80, -4.60], [50.50, 1.40], [53.00, 5.00], [54.00, 8.00],
            ]],
            ['Port of Gdansk', 'Port of Rotterdam', [
                [54.70, 17.00], [55.00, 13.00], [55.40, 10.70], [55.60, 7.00], [53.60, 4.20],
            ]],

            // --- Baltic ---
            ['Port of Hamburg', 'Port of Gdansk', [
                [54.50, 9.80], [54.60, 11.20], [54.55, 13.50], [54.45, 15.80],
            ]],
            ['Port of Gdansk', 'Port of Gdynia', [[54.55, 18.70]]],
            ['Port of Gdansk', 'Port of Szczecin', [[54.70, 17.50], [54.30, 14.50], [53.90, 14.20]]],
            ['Port of Klaipeda', 'Port of Gdansk', [[55.30, 19.50], [54.70, 18.50]]],
            ['Port of Klaipeda', 'Port of Riga', [[56.50, 20.40], [57.00, 23.70]]],
            ['Port of Riga', 'Port of Tallinn', [[58.00, 23.50], [59.20, 24.30]]],
            ['Port of Tallinn', 'Port of Helsinki', [[59.60, 24.70]]],
            ['Port of Helsinki', 'Port of Stockholm', [[59.70, 22.80], [59.50, 19.50]]],
            ['Port of Stockholm', 'Port of Tallinn', [[59.20, 21.00], [59.30, 23.80]]],
            ['Port of Gothenburg', 'Port of Helsinki', [[57.00, 12.30], [55.60, 13.50], [55.30, 15.00], [57.20, 19.00], [59.50, 23.80]]],
            ['Port of Copenhagen', 'Port of Gothenburg', [[55.90, 12.70], [56.80, 12.00]]],
            ['Port of Copenhagen', 'Port of Aarhus', [[56.20, 11.50]]],
            ['Port of Oslo', 'Port of Gothenburg', [[59.10, 10.70], [58.20, 11.30]]],
            ['Port of Oslo', 'Port of Copenhagen', [[58.90, 10.70], [57.80, 10.70], [56.40, 11.40]]],
            ['Port of Aarhus', 'Port of Gothenburg', [[56.80, 11.00]]],
            ['Port of Hamburg', 'Port of Aarhus', [[54.40, 9.20], [55.50, 10.30], [56.10, 10.30]]],

            // --- Suez / Levant / Gulf gateways ---
            ['Port of Piraeus', 'Port of Port Said', [[35.60, 25.00], [33.40, 28.00], [31.30, 32.30]]],
            ['Port of Port Said', 'Port of Alexandria', [[31.40, 31.30]]],
            ['Port of Port Said', 'Port of Damietta', [[31.50, 31.80]]],
            ['Port of Port Said', 'Port of Haifa', [[31.80, 33.50], [32.80, 34.90]]],
            ['Port of Piraeus', 'Port of Haifa', [[36.00, 25.00], [34.00, 29.00], [32.80, 34.90]]],
            ['Port of Port Said', 'Port of Limassol', [[32.20, 32.80], [34.60, 33.00]]],
            ['Port of Port Said', 'Port of Jebel Ali', [[29.90, 32.55], [27.90, 33.80], [23.00, 37.50], [18.00, 41.00], [15.00, 50.00], [22.00, 58.00], [25.00, 55.00]]],
            ['Port of Jebel Ali', 'Khalifa Port', [[24.80, 54.60]]],
            ['Port of Gioia Tauro', 'Port of Port Said', [[38.00, 16.50], [35.00, 20.00], [33.00, 28.00], [31.30, 32.30]]],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasColumn('routes', 'sea_path')) {
            return;
        }

        $names = [];
        foreach ($this->symmetricPatches() as [$from, $to, $_waypoints]) {
            $names[$from] = true;
            $names[$to] = true;
        }

        $portIds = DB::table('ports')->whereIn('name', array_keys($names))->pluck('id', 'name');

        $now = now();
        foreach ($this->symmetricPatches() as [$from, $to, $waypoints]) {
            $fromId = $portIds[$from] ?? null;
            $toId = $portIds[$to] ?? null;
            if (! $fromId || ! $toId) {
                continue;
            }

            $this->fillIfNull($fromId, $toId, $waypoints, $now);
            $this->fillIfNull($toId, $fromId, array_reverse($waypoints), $now);
        }
    }

    /**
     * @param  list<array{0:float,1:float}>  $waypoints
     */
    private function fillIfNull(int $originId, int $destinationId, array $waypoints, \DateTimeInterface $now): void
    {
        DB::table('routes')
            ->where('origin_port_id', $originId)
            ->where('destination_port_id', $destinationId)
            ->whereNull('sea_path')
            ->update(['sea_path' => json_encode(array_values($waypoints)), 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Intentionally non-destructive: filled rows stay (operator can clear sea_path manually if needed).
    }
};
