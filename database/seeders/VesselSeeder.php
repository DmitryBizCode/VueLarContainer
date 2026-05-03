<?php

namespace Database\Seeders;

use App\Models\Port;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class VesselSeeder extends Seeder
{
    public function run(): void
    {
        $portIds = Port::query()->pluck('id', 'name');
        $now = now();

        $hubPorts = array_values(array_unique(array_filter([
            $portIds['Port of Rotterdam'] ?? null,
            $portIds['Port of Hamburg'] ?? null,
            $portIds['Port of Antwerp'] ?? null,
            $portIds['Port of Gdansk'] ?? null,
            $portIds['Port of Valencia'] ?? null,
            $portIds['Port of Piraeus'] ?? null,
            $portIds['Port of Felixstowe'] ?? null,
            $portIds['Port of Odesa'] ?? null,
            $portIds['Port of Genoa'] ?? null,
            $portIds['Port of Bremerhaven'] ?? null,
            $portIds['Port of Le Havre'] ?? null,
            $portIds['Port of Barcelona'] ?? null,
        ])));

        if ($hubPorts !== [] && ! Vessel::query()->where('imo_number', 'like', '910%')->exists()) {
            $statuses = ['active', 'active', 'active', 'in_port', 'in_transit', 'scheduled'];
            for ($i = 1; $i <= 28; $i++) {
                $portId = $hubPorts[($i - 1) % count($hubPorts)];
                $imo = str_pad((string) (9100000 + $i), 7, '0', STR_PAD_LEFT);
                Vessel::query()->create([
                    'name' => sprintf('MV Baltic Runner %02d', $i),
                    'imo_number' => $imo,
                    'capacity_teu' => 1800 + ($i * 80),
                    'status' => $statuses[$i % count($statuses)],
                    'last_inspection_date' => $now->copy()->subMonths(($i % 8) + 1)->toDateString(),
                    'current_port_id' => $portId,
                    'berth_busy_until' => null,
                    'out_of_service_until' => null,
                ]);
            }
        }

        // Extra fleet covering secondary ports so smaller allowlist ports actually have assignable vessels.
        if (! Vessel::query()->where('imo_number', 'like', '911%')->exists()) {
            $extraPorts = array_values(array_unique(array_filter([
                $portIds['Port of Amsterdam'] ?? null,
                $portIds['Port of Zeebrugge'] ?? null,
                $portIds['Port of Dunkirk'] ?? null,
                $portIds['Port of Southampton'] ?? null,
                $portIds['Port of London'] ?? null,
                $portIds['Port of Dublin'] ?? null,
                $portIds['Port of Cork'] ?? null,
                $portIds['Port of Wilhelmshaven'] ?? null,
                $portIds['Port of Kiel'] ?? null,
                $portIds['Port of Aarhus'] ?? null,
                $portIds['Port of Copenhagen'] ?? null,
                $portIds['Port of Gothenburg'] ?? null,
                $portIds['Port of Oslo'] ?? null,
                $portIds['Port of Stockholm'] ?? null,
                $portIds['Port of Helsinki'] ?? null,
                $portIds['Port of Tallinn'] ?? null,
                $portIds['Port of Riga'] ?? null,
                $portIds['Port of Klaipeda'] ?? null,
                $portIds['Port of Gdynia'] ?? null,
                $portIds['Port of Szczecin'] ?? null,
                $portIds['Port of Bilbao'] ?? null,
                $portIds['Port of Vigo'] ?? null,
                $portIds['Port of Leixoes'] ?? null,
                $portIds['Port of Lisbon'] ?? null,
                $portIds['Port of Sines'] ?? null,
                $portIds['Port of Algeciras'] ?? null,
                $portIds['Port of Marseille'] ?? null,
                $portIds['Port of La Spezia'] ?? null,
                $portIds['Port of Livorno'] ?? null,
                $portIds['Port of Naples'] ?? null,
                $portIds['Port of Taranto'] ?? null,
                $portIds['Port of Gioia Tauro'] ?? null,
                $portIds['Port of Venice'] ?? null,
                $portIds['Port of Ravenna'] ?? null,
                $portIds['Port of Trieste'] ?? null,
                $portIds['Port of Koper'] ?? null,
                $portIds['Port of Thessaloniki'] ?? null,
                $portIds['Port of Istanbul Ambarli'] ?? null,
                $portIds['Port of Constanta'] ?? null,
                $portIds['Malta Freeport'] ?? null,
                $portIds['Port of Limassol'] ?? null,
                $portIds['Tanger Med'] ?? null,
                $portIds['Port of Port Said'] ?? null,
            ])));

            $barge = ['MV Coastal Trader', 'MV Sea Linker', 'MV North Pioneer', 'MV Atlantic Voyager', 'MV Aegean Spirit'];
            $count = count($extraPorts);
            for ($i = 1; $i <= $count * 2; $i++) {
                $portId = $extraPorts[($i - 1) % $count];
                $imo = str_pad((string) (9110000 + $i), 7, '0', STR_PAD_LEFT);
                Vessel::query()->create([
                    'name' => sprintf('%s %02d', $barge[$i % count($barge)], $i),
                    'imo_number' => $imo,
                    'capacity_teu' => 900 + ($i * 40),
                    // All extra vessels start active so they are assignable immediately.
                    'status' => 'active',
                    'last_inspection_date' => $now->copy()->subMonths(($i % 10) + 1)->toDateString(),
                    'current_port_id' => $portId,
                    'berth_busy_until' => null,
                    'out_of_service_until' => null,
                ]);
            }
        }
    }
}
