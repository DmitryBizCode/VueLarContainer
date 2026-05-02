<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waypoint list mirrors RouteSeeder::applyRegionalSeaPaths so DBs seeded before
     * the sea_path column landed still get correct maritime geometry. Fills only
     * rows where sea_path IS NULL to avoid clobbering operator customizations.
     *
     * @return list<array{0:string,1:string,2:list<array{0:float,1:float}>}>
     */
    private function patches(): array
    {
        return [
            // North Sea / Channel
            ['Port of Hamburg', 'Port of Rotterdam', [[54.18, 7.85], [53.65, 6.05]]],
            ['Port of Rotterdam', 'Port of Felixstowe', [[52.55, 3.35], [51.95, 2.05]]],
            ['Port of Felixstowe', 'Port of London', [[52.20, 1.60], [51.90, 1.10], [51.60, 0.80]]],
            ['Port of Bordeaux', 'Port of Le Havre', [[47.50, -1.80]]],

            // Iberia / Bay of Biscay / Portuguese Atlantic
            ['Port of Bilbao', 'Port of Vigo', [[43.85, -4.15], [43.15, -6.05], [42.42, -7.85]]],
            ['Port of Vigo', 'Port of Bilbao', [[42.42, -7.85], [43.15, -6.05], [43.85, -4.15]]],
            ['Port of Vigo', 'Port of Leixoes', [[41.28, -8.85], [41.15, -8.78]]],
            ['Port of Leixoes', 'Port of Vigo', [[41.15, -8.78], [41.28, -8.85]]],
            ['Port of Valencia', 'Port of Bilbao', [[40.15, -1.35], [41.55, -2.65], [42.75, -3.15]]],
            ['Port of Bilbao', 'Port of Valencia', [[42.75, -3.15], [41.55, -2.65], [40.15, -1.35]]],
            ['Port of Valencia', 'Port of Algeciras', [[38.35, -0.55], [37.10, -2.20], [36.45, -4.20], [36.18, -5.35]]],
            ['Port of Algeciras', 'Port of Valencia', [[36.18, -5.35], [36.45, -4.20], [37.10, -2.20], [38.35, -0.55]]],
            ['Port of Algeciras', 'Port of Sines', [[36.22, -6.85], [37.15, -8.55], [37.88, -8.92]]],
            ['Port of Sines', 'Port of Algeciras', [[37.88, -8.92], [37.15, -8.55], [36.22, -6.85]]],
            ['Port of Barcelona', 'Port of Marseille', [[41.05, 2.85], [42.10, 4.25], [42.85, 5.05]]],
            ['Port of Marseille', 'Port of Barcelona', [[42.85, 5.05], [42.10, 4.25], [41.05, 2.85]]],

            // Eastern Atlantic long legs (kept west of continental shelf)
            ['Port of Le Havre', 'Port of Leixoes', [[48.00, -4.50], [46.50, -7.20], [43.80, -10.50]]],
            ['Port of Leixoes', 'Port of Le Havre', [[43.80, -10.50], [46.50, -7.20], [48.00, -4.50]]],
            ['Port of Sines', 'Port of Le Havre', [[40.20, -11.50], [44.80, -8.50], [47.20, -4.80]]],
            ['Port of Le Havre', 'Port of Sines', [[47.20, -4.80], [44.80, -8.50], [40.20, -11.50]]],
            ['Port of Lisbon', 'Port of Le Havre', [[39.20, -11.80], [44.50, -8.00], [47.80, -4.60]]],
            ['Port of Le Havre', 'Port of Lisbon', [[47.80, -4.60], [44.50, -8.00], [39.20, -11.80]]],

            // Le Havre ↔ Marseille via Atlantic / Gibraltar / Mediterranean
            ['Port of Le Havre', 'Port of Marseille', [
                [48.20, -4.85], [46.50, -6.80], [44.50, -8.50], [43.00, -9.30],
                [40.50, -9.50], [37.95, -8.90], [36.40, -6.20], [36.00, -4.00],
                [36.50, -0.50], [38.50, 1.00], [40.50, 3.00], [42.80, 5.00],
            ]],
            ['Port of Marseille', 'Port of Le Havre', [
                [42.80, 5.00], [40.50, 3.00], [38.50, 1.00], [36.50, -0.50],
                [36.00, -4.00], [36.40, -6.20], [37.95, -8.90], [40.50, -9.50],
                [43.00, -9.30], [44.50, -8.50], [46.50, -6.80], [48.20, -4.85],
            ]],

            // Hamburg ↔ Gdansk via Kiel Bight / Baltic Sea
            ['Port of Hamburg', 'Port of Gdansk', [
                [54.50, 9.80], [54.60, 11.20], [54.55, 13.50], [54.45, 15.80],
            ]],
            ['Port of Gdansk', 'Port of Hamburg', [
                [54.45, 15.80], [54.55, 13.50], [54.60, 11.20], [54.50, 9.80],
            ]],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasColumn('routes', 'sea_path')) {
            return;
        }

        $names = [];
        foreach ($this->patches() as [$from, $to, $_waypoints]) {
            $names[$from] = true;
            $names[$to] = true;
        }

        $portIds = DB::table('ports')->whereIn('name', array_keys($names))->pluck('id', 'name');

        $now = now();
        foreach ($this->patches() as [$from, $to, $waypoints]) {
            $fromId = $portIds[$from] ?? null;
            $toId = $portIds[$to] ?? null;
            if (! $fromId || ! $toId) {
                continue;
            }
            DB::table('routes')
                ->where('origin_port_id', $fromId)
                ->where('destination_port_id', $toId)
                ->whereNull('sea_path')
                ->update(['sea_path' => json_encode($waypoints), 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('routes', 'sea_path')) {
            return;
        }

        $names = [];
        foreach ($this->patches() as [$from, $to, $_waypoints]) {
            $names[$from] = true;
            $names[$to] = true;
        }

        $portIds = DB::table('ports')->whereIn('name', array_keys($names))->pluck('id', 'name');

        foreach ($this->patches() as [$from, $to, $_waypoints]) {
            $fromId = $portIds[$from] ?? null;
            $toId = $portIds[$to] ?? null;
            if (! $fromId || ! $toId) {
                continue;
            }
            DB::table('routes')
                ->where('origin_port_id', $fromId)
                ->where('destination_port_id', $toId)
                ->update(['sea_path' => null, 'updated_at' => now()]);
        }
    }
};
