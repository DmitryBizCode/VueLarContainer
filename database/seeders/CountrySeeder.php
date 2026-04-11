<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    private const PHONE_CODES = [
        'UA' => '+380', 'PL' => '+48', 'DE' => '+49', 'FR' => '+33', 'ES' => '+34', 'IT' => '+39',
        'GB' => '+44', 'IE' => '+353', 'NL' => '+31', 'BE' => '+32', 'SE' => '+46', 'NO' => '+47',
        'DK' => '+45', 'FI' => '+358', 'CZ' => '+420', 'SK' => '+421', 'RO' => '+40', 'HU' => '+36',
        'PT' => '+351', 'AT' => '+43', 'CH' => '+41', 'TR' => '+90', 'US' => '+1', 'CA' => '+1',
        'AU' => '+61', 'JP' => '+81',
    ];

    public function run(): void
    {
        $isoCodes = array_keys(self::PHONE_CODES);
        $existing = DB::table('countries')->whereIn('iso_code', $isoCodes)->pluck('iso_code')->toArray();

        $now = now();
        $phoneCodes = self::PHONE_CODES;

        if (count($existing) > 0) {
            foreach ($isoCodes as $code) {
                if (isset($phoneCodes[$code])) {
                    DB::table('countries')->where('iso_code', $code)->update(['phone_code' => $phoneCodes[$code], 'updated_at' => $now]);
                }
            }

            return;
        }

        DB::table('countries')->insert([
            ['name' => 'Ukraine', 'iso_code' => 'UA', 'phone_code' => $phoneCodes['UA'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Poland', 'iso_code' => 'PL', 'phone_code' => $phoneCodes['PL'], 'interest_tax' => 23.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Germany', 'iso_code' => 'DE', 'phone_code' => $phoneCodes['DE'], 'interest_tax' => 19.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'France', 'iso_code' => 'FR', 'phone_code' => $phoneCodes['FR'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Spain', 'iso_code' => 'ES', 'phone_code' => $phoneCodes['ES'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Italy', 'iso_code' => 'IT', 'phone_code' => $phoneCodes['IT'], 'interest_tax' => 22.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'United Kingdom', 'iso_code' => 'GB', 'phone_code' => $phoneCodes['GB'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ireland', 'iso_code' => 'IE', 'phone_code' => $phoneCodes['IE'], 'interest_tax' => 23.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Netherlands', 'iso_code' => 'NL', 'phone_code' => $phoneCodes['NL'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Belgium', 'iso_code' => 'BE', 'phone_code' => $phoneCodes['BE'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sweden', 'iso_code' => 'SE', 'phone_code' => $phoneCodes['SE'], 'interest_tax' => 25.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Norway', 'iso_code' => 'NO', 'phone_code' => $phoneCodes['NO'], 'interest_tax' => 25.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Denmark', 'iso_code' => 'DK', 'phone_code' => $phoneCodes['DK'], 'interest_tax' => 25.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finland', 'iso_code' => 'FI', 'phone_code' => $phoneCodes['FI'], 'interest_tax' => 24.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Czech Republic', 'iso_code' => 'CZ', 'phone_code' => $phoneCodes['CZ'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Slovakia', 'iso_code' => 'SK', 'phone_code' => $phoneCodes['SK'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Romania', 'iso_code' => 'RO', 'phone_code' => $phoneCodes['RO'], 'interest_tax' => 19.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hungary', 'iso_code' => 'HU', 'phone_code' => $phoneCodes['HU'], 'interest_tax' => 27.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Portugal', 'iso_code' => 'PT', 'phone_code' => $phoneCodes['PT'], 'interest_tax' => 23.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Austria', 'iso_code' => 'AT', 'phone_code' => $phoneCodes['AT'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Switzerland', 'iso_code' => 'CH', 'phone_code' => $phoneCodes['CH'], 'interest_tax' => 8.10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Turkey', 'iso_code' => 'TR', 'phone_code' => $phoneCodes['TR'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'United States', 'iso_code' => 'US', 'phone_code' => $phoneCodes['US'], 'interest_tax' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Canada', 'iso_code' => 'CA', 'phone_code' => $phoneCodes['CA'], 'interest_tax' => 5.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Australia', 'iso_code' => 'AU', 'phone_code' => $phoneCodes['AU'], 'interest_tax' => 10.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Japan', 'iso_code' => 'JP', 'phone_code' => $phoneCodes['JP'], 'interest_tax' => 10.00, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
