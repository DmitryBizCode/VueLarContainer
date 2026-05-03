<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Port;
use Illuminate\Database\Seeder;

class PortSeeder extends Seeder
{
    /**
     * Major European / Black Sea container ports with approximate coordinates.
     *
     * @return list<array{name: string, city: string, iso: string, lat: float, lng: float}>
     */
    private function portDefinitions(): array
    {
        return [
            ['name' => 'Port of Odesa', 'city' => 'Odesa', 'iso' => 'UA', 'lat' => 46.4858, 'lng' => 30.7395],
            ['name' => 'Port of Constanta', 'city' => 'Constanta', 'iso' => 'RO', 'lat' => 44.1733, 'lng' => 28.6383],
            ['name' => 'Port of Piraeus', 'city' => 'Piraeus', 'iso' => 'GR', 'lat' => 37.9420, 'lng' => 23.6462],
            ['name' => 'Port of Koper', 'city' => 'Koper', 'iso' => 'SI', 'lat' => 45.5481, 'lng' => 13.7301],
            ['name' => 'Port of Genoa', 'city' => 'Genoa', 'iso' => 'IT', 'lat' => 44.4056, 'lng' => 8.9463],
            ['name' => 'Port of Trieste', 'city' => 'Trieste', 'iso' => 'IT', 'lat' => 45.6495, 'lng' => 13.7768],
            ['name' => 'Port of La Spezia', 'city' => 'La Spezia', 'iso' => 'IT', 'lat' => 44.1030, 'lng' => 9.8241],
            ['name' => 'Port of Livorno', 'city' => 'Livorno', 'iso' => 'IT', 'lat' => 43.5485, 'lng' => 10.3106],
            ['name' => 'Port of Gioia Tauro', 'city' => 'Gioia Tauro', 'iso' => 'IT', 'lat' => 38.4246, 'lng' => 15.9000],
            ['name' => 'Port of Venice', 'city' => 'Venice', 'iso' => 'IT', 'lat' => 45.4408, 'lng' => 12.3155],
            ['name' => 'Port of Ravenna', 'city' => 'Ravenna', 'iso' => 'IT', 'lat' => 44.4184, 'lng' => 12.2035],
            ['name' => 'Port of Naples', 'city' => 'Naples', 'iso' => 'IT', 'lat' => 40.8518, 'lng' => 14.2681],
            ['name' => 'Port of Taranto', 'city' => 'Taranto', 'iso' => 'IT', 'lat' => 40.4644, 'lng' => 17.2470],
            ['name' => 'Port of Barcelona', 'city' => 'Barcelona', 'iso' => 'ES', 'lat' => 41.3508, 'lng' => 2.1631],
            ['name' => 'Port of Valencia', 'city' => 'Valencia', 'iso' => 'ES', 'lat' => 39.4461, 'lng' => -0.3199],
            ['name' => 'Port of Bilbao', 'city' => 'Bilbao', 'iso' => 'ES', 'lat' => 43.3452, 'lng' => -3.0361],
            ['name' => 'Port of Algeciras', 'city' => 'Algeciras', 'iso' => 'ES', 'lat' => 36.1312, 'lng' => -5.4474],
            ['name' => 'Port of Vigo', 'city' => 'Vigo', 'iso' => 'ES', 'lat' => 42.2406, 'lng' => -8.7207],
            ['name' => 'Port of Sines', 'city' => 'Sines', 'iso' => 'PT', 'lat' => 37.9562, 'lng' => -8.8696],
            ['name' => 'Port of Lisbon', 'city' => 'Lisbon', 'iso' => 'PT', 'lat' => 38.7223, 'lng' => -9.1393],
            ['name' => 'Port of Leixoes', 'city' => 'Porto', 'iso' => 'PT', 'lat' => 41.1829, 'lng' => -8.7030],
            ['name' => 'Port of Le Havre', 'city' => 'Le Havre', 'iso' => 'FR', 'lat' => 49.4938, 'lng' => 0.1077],
            ['name' => 'Port of Marseille', 'city' => 'Marseille', 'iso' => 'FR', 'lat' => 43.3102, 'lng' => 5.3679],
            ['name' => 'Port of Dunkirk', 'city' => 'Dunkirk', 'iso' => 'FR', 'lat' => 51.0450, 'lng' => 2.3772],
            // Use the ocean-facing entrance of the Gironde estuary (Le Verdon-sur-Mer) to avoid land-crossing polylines.
            ['name' => 'Port of Bordeaux', 'city' => 'Bordeaux', 'iso' => 'FR', 'lat' => 45.5510, 'lng' => -1.0610],
            ['name' => 'Port of Antwerp', 'city' => 'Antwerp', 'iso' => 'BE', 'lat' => 51.2792, 'lng' => 4.3500],
            ['name' => 'Port of Zeebrugge', 'city' => 'Bruges', 'iso' => 'BE', 'lat' => 51.3494, 'lng' => 3.2047],
            ['name' => 'Port of Rotterdam', 'city' => 'Rotterdam', 'iso' => 'NL', 'lat' => 51.9244, 'lng' => 4.4777],
            ['name' => 'Port of Amsterdam', 'city' => 'Amsterdam', 'iso' => 'NL', 'lat' => 52.4105, 'lng' => 4.8292],
            ['name' => 'Port of Hamburg', 'city' => 'Hamburg', 'iso' => 'DE', 'lat' => 53.5511, 'lng' => 9.9937],
            ['name' => 'Port of Bremerhaven', 'city' => 'Bremerhaven', 'iso' => 'DE', 'lat' => 53.5396, 'lng' => 8.5809],
            ['name' => 'Port of Wilhelmshaven', 'city' => 'Wilhelmshaven', 'iso' => 'DE', 'lat' => 53.5290, 'lng' => 8.1120],
            ['name' => 'Port of Kiel', 'city' => 'Kiel', 'iso' => 'DE', 'lat' => 54.3233, 'lng' => 10.1228],
            ['name' => 'Port of Gdansk', 'city' => 'Gdansk', 'iso' => 'PL', 'lat' => 54.3520, 'lng' => 18.6466],
            ['name' => 'Port of Szczecin', 'city' => 'Szczecin', 'iso' => 'PL', 'lat' => 53.4285, 'lng' => 14.5528],
            ['name' => 'Port of Gdynia', 'city' => 'Gdynia', 'iso' => 'PL', 'lat' => 54.5189, 'lng' => 18.5305],
            ['name' => 'Port of Southampton', 'city' => 'Southampton', 'iso' => 'GB', 'lat' => 50.8969, 'lng' => -1.3964],
            ['name' => 'Port of Felixstowe', 'city' => 'Felixstowe', 'iso' => 'GB', 'lat' => 51.9536, 'lng' => 1.3511],
            // Use Thames estuary / Tilbury area to keep maritime lines on water.
            ['name' => 'Port of London', 'city' => 'London', 'iso' => 'GB', 'lat' => 51.4580, 'lng' => 0.3550],
            ['name' => 'Port of Dublin', 'city' => 'Dublin', 'iso' => 'IE', 'lat' => 53.3438, 'lng' => -6.2146],
            ['name' => 'Port of Cork', 'city' => 'Cork', 'iso' => 'IE', 'lat' => 51.8985, 'lng' => -8.4756],
            ['name' => 'Port of Aarhus', 'city' => 'Aarhus', 'iso' => 'DK', 'lat' => 56.1572, 'lng' => 10.2107],
            ['name' => 'Port of Copenhagen', 'city' => 'Copenhagen', 'iso' => 'DK', 'lat' => 55.6761, 'lng' => 12.5683],
            ['name' => 'Port of Gothenburg', 'city' => 'Gothenburg', 'iso' => 'SE', 'lat' => 57.6867, 'lng' => 11.8531],
            ['name' => 'Port of Stockholm', 'city' => 'Stockholm', 'iso' => 'SE', 'lat' => 59.3293, 'lng' => 18.0686],
            ['name' => 'Port of Oslo', 'city' => 'Oslo', 'iso' => 'NO', 'lat' => 59.9139, 'lng' => 10.7522],
            ['name' => 'Port of Helsinki', 'city' => 'Helsinki', 'iso' => 'FI', 'lat' => 60.1713, 'lng' => 24.9410],
            ['name' => 'Port of Tallinn', 'city' => 'Tallinn', 'iso' => 'EE', 'lat' => 59.4419, 'lng' => 24.7656],
            ['name' => 'Port of Riga', 'city' => 'Riga', 'iso' => 'LV', 'lat' => 56.9639, 'lng' => 24.0914],
            ['name' => 'Port of Klaipeda', 'city' => 'Klaipeda', 'iso' => 'LT', 'lat' => 55.7033, 'lng' => 21.1443],
            ['name' => 'Malta Freeport', 'city' => 'Marsaxlokk', 'iso' => 'MT', 'lat' => 35.8415, 'lng' => 14.5431],
            ['name' => 'Port of Limassol', 'city' => 'Limassol', 'iso' => 'CY', 'lat' => 34.6786, 'lng' => 33.0416],
            ['name' => 'Port of Thessaloniki', 'city' => 'Thessaloniki', 'iso' => 'GR', 'lat' => 40.6401, 'lng' => 22.9444],
            ['name' => 'Port of Istanbul Ambarli', 'city' => 'Istanbul', 'iso' => 'TR', 'lat' => 40.9690, 'lng' => 28.6790],
            ['name' => 'Port of Singapore', 'city' => 'Singapore', 'iso' => 'SG', 'lat' => 1.2640, 'lng' => 103.8230],
            ['name' => 'Port of Jebel Ali', 'city' => 'Dubai', 'iso' => 'AE', 'lat' => 25.0117, 'lng' => 55.0556],
            ['name' => 'Khalifa Port', 'city' => 'Abu Dhabi', 'iso' => 'AE', 'lat' => 24.7869, 'lng' => 54.6497],
            ['name' => 'Port of Shanghai', 'city' => 'Shanghai', 'iso' => 'CN', 'lat' => 31.2304, 'lng' => 121.4737],
            ['name' => 'Port of Port Said', 'city' => 'Port Said', 'iso' => 'EG', 'lat' => 31.2657, 'lng' => 32.3019],
            ['name' => 'Port of Alexandria', 'city' => 'Alexandria', 'iso' => 'EG', 'lat' => 31.2001, 'lng' => 29.9187],
            ['name' => 'Port of Damietta', 'city' => 'Damietta', 'iso' => 'EG', 'lat' => 31.4529, 'lng' => 31.8200],
            ['name' => 'Tanger Med', 'city' => 'Tangier', 'iso' => 'MA', 'lat' => 35.8800, 'lng' => -5.5000],
            ['name' => 'Port of Casablanca', 'city' => 'Casablanca', 'iso' => 'MA', 'lat' => 33.6044, 'lng' => -7.6236],
            ['name' => 'Port of Algiers', 'city' => 'Algiers', 'iso' => 'DZ', 'lat' => 36.7538, 'lng' => 3.0588],
            ['name' => 'Port of Rades', 'city' => 'Tunis', 'iso' => 'TN', 'lat' => 36.7680, 'lng' => 10.2730],
            ['name' => 'Port of Haifa', 'city' => 'Haifa', 'iso' => 'IL', 'lat' => 32.8191, 'lng' => 34.9983],
            ['name' => 'Port of Los Angeles', 'city' => 'Los Angeles', 'iso' => 'US', 'lat' => 33.7542, 'lng' => -118.2165],
            ['name' => 'Port of New York', 'city' => 'New York', 'iso' => 'US', 'lat' => 40.6681, 'lng' => -74.0451],
            ['name' => 'Port of Yokohama', 'city' => 'Yokohama', 'iso' => 'JP', 'lat' => 35.4437, 'lng' => 139.6380],
        ];
    }

    public function run(): void
    {
        foreach ($this->portDefinitions() as $def) {
            $countryId = Country::query()->where('iso_code', $def['iso'])->value('id');
            if ($countryId === null) {
                continue;
            }

            Port::updateOrCreate(
                ['name' => $def['name']],
                [
                    'country_id' => $countryId,
                    'city' => $def['city'],
                    'latitude' => $def['lat'],
                    'longitude' => $def['lng'],
                ]
            );
        }
    }
}
