<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PortSeeder extends Seeder
{
    public function run(): void
    {
        $portNames = ['Port of Odesa', 'Port of Hamburg', 'Port of Gdansk', 'Port of Rotterdam', 'Port of Valencia'];
        if (DB::table('ports')->whereIn('name', $portNames)->exists()) {
            return;
        }

        $countryIdsByIso = DB::table('countries')
            ->whereIn('iso_code', ['UA', 'DE', 'PL', 'NL', 'ES'])
            ->pluck('id', 'iso_code');

        $now = now();
        DB::table('ports')->insert([
            [
                'country_id' => $countryIdsByIso['UA'] ?? null,
                'name' => 'Port of Odesa',
                'city' => 'Odesa',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_id' => $countryIdsByIso['DE'] ?? null,
                'name' => 'Port of Hamburg',
                'city' => 'Hamburg',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_id' => $countryIdsByIso['PL'] ?? null,
                'name' => 'Port of Gdansk',
                'city' => 'Gdansk',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_id' => $countryIdsByIso['NL'] ?? null,
                'name' => 'Port of Rotterdam',
                'city' => 'Rotterdam',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country_id' => $countryIdsByIso['ES'] ?? null,
                'name' => 'Port of Valencia',
                'city' => 'Valencia',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
