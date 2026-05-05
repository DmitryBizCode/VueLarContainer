<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    private const PHONE_CODES = [
        'UA' => '+380', 'PL' => '+48', 'DE' => '+49', 'FR' => '+33', 'ES' => '+34', 'IT' => '+39',
        'GB' => '+44', 'IE' => '+353', 'NL' => '+31', 'BE' => '+32', 'SE' => '+46', 'NO' => '+47',
        'DK' => '+45', 'FI' => '+358', 'CZ' => '+420', 'SK' => '+421', 'RO' => '+40', 'HU' => '+36',
        'PT' => '+351', 'AT' => '+43', 'CH' => '+41', 'TR' => '+90', 'US' => '+1', 'CA' => '+1',
        'AU' => '+61', 'JP' => '+81',
        'HR' => '+385', 'SI' => '+386', 'GR' => '+30', 'EE' => '+372', 'LV' => '+371', 'LT' => '+370',
        'MT' => '+356', 'CY' => '+357',
        'SG' => '+65', 'AE' => '+971', 'EG' => '+20', 'CN' => '+86',
        // North Africa / Middle East (for expanded ports)
        'MA' => '+212', 'DZ' => '+213', 'TN' => '+216', 'IL' => '+972',
        // Americas / Africa / Oceania (UNLOCODE-backed ports in PortSeeder)
        'BR' => '+55', 'AR' => '+54', 'CL' => '+56', 'PE' => '+51', 'CO' => '+57', 'MX' => '+52',
        'ZA' => '+27', 'KE' => '+254', 'NG' => '+234', 'NA' => '+264', 'NZ' => '+64',
        // Extra hubs (ports.csv) — Asia / Central America / East Africa
        'KR' => '+82', 'IN' => '+91', 'LK' => '+94', 'MY' => '+60', 'TZ' => '+255', 'PA' => '+507',
    ];

    public function run(): void
    {
        $isoCodes = array_keys(self::PHONE_CODES);
        $existing = Country::query()->whereIn('iso_code', $isoCodes)->pluck('iso_code')->toArray();

        $now = now();
        $phoneCodes = self::PHONE_CODES;

        if (count($existing) > 0) {
            foreach ($isoCodes as $code) {
                if (isset($phoneCodes[$code])) {
                    Country::query()->where('iso_code', $code)->update(['phone_code' => $phoneCodes[$code], 'updated_at' => $now]);
                }
            }
            $this->ensureExtendedCountries($phoneCodes, $now);

            return;
        }

        Country::insert([
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
            ['name' => 'Croatia', 'iso_code' => 'HR', 'phone_code' => $phoneCodes['HR'], 'interest_tax' => 25.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Slovenia', 'iso_code' => 'SI', 'phone_code' => $phoneCodes['SI'], 'interest_tax' => 22.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Greece', 'iso_code' => 'GR', 'phone_code' => $phoneCodes['GR'], 'interest_tax' => 24.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Estonia', 'iso_code' => 'EE', 'phone_code' => $phoneCodes['EE'], 'interest_tax' => 22.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Latvia', 'iso_code' => 'LV', 'phone_code' => $phoneCodes['LV'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lithuania', 'iso_code' => 'LT', 'phone_code' => $phoneCodes['LT'], 'interest_tax' => 21.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Malta', 'iso_code' => 'MT', 'phone_code' => $phoneCodes['MT'], 'interest_tax' => 18.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cyprus', 'iso_code' => 'CY', 'phone_code' => $phoneCodes['CY'], 'interest_tax' => 19.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Singapore', 'iso_code' => 'SG', 'phone_code' => $phoneCodes['SG'], 'interest_tax' => 9.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'United Arab Emirates', 'iso_code' => 'AE', 'phone_code' => $phoneCodes['AE'], 'interest_tax' => 5.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Egypt', 'iso_code' => 'EG', 'phone_code' => $phoneCodes['EG'], 'interest_tax' => 14.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'China', 'iso_code' => 'CN', 'phone_code' => $phoneCodes['CN'], 'interest_tax' => 13.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Morocco', 'iso_code' => 'MA', 'phone_code' => $phoneCodes['MA'], 'interest_tax' => 20.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Algeria', 'iso_code' => 'DZ', 'phone_code' => $phoneCodes['DZ'], 'interest_tax' => 19.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tunisia', 'iso_code' => 'TN', 'phone_code' => $phoneCodes['TN'], 'interest_tax' => 19.00, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Israel', 'iso_code' => 'IL', 'phone_code' => $phoneCodes['IL'], 'interest_tax' => 17.00, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->ensureExtendedCountries($phoneCodes, $now);
    }

    /**
     * Adds newer EU / neighbour countries when DB was seeded before those rows existed.
     *
     * @param  array<string, string>  $phoneCodes
     */
    private function ensureExtendedCountries(array $phoneCodes, \DateTimeInterface $now): void
    {
        $extra = [
            ['name' => 'Croatia', 'iso_code' => 'HR', 'interest_tax' => 25.00],
            ['name' => 'Slovenia', 'iso_code' => 'SI', 'interest_tax' => 22.00],
            ['name' => 'Greece', 'iso_code' => 'GR', 'interest_tax' => 24.00],
            ['name' => 'Estonia', 'iso_code' => 'EE', 'interest_tax' => 22.00],
            ['name' => 'Latvia', 'iso_code' => 'LV', 'interest_tax' => 21.00],
            ['name' => 'Lithuania', 'iso_code' => 'LT', 'interest_tax' => 21.00],
            ['name' => 'Malta', 'iso_code' => 'MT', 'interest_tax' => 18.00],
            ['name' => 'Cyprus', 'iso_code' => 'CY', 'interest_tax' => 19.00],
            ['name' => 'Singapore', 'iso_code' => 'SG', 'interest_tax' => 9.00],
            ['name' => 'United Arab Emirates', 'iso_code' => 'AE', 'interest_tax' => 5.00],
            ['name' => 'Egypt', 'iso_code' => 'EG', 'interest_tax' => 14.00],
            ['name' => 'China', 'iso_code' => 'CN', 'interest_tax' => 13.00],
            ['name' => 'Morocco', 'iso_code' => 'MA', 'interest_tax' => 20.00],
            ['name' => 'Algeria', 'iso_code' => 'DZ', 'interest_tax' => 19.00],
            ['name' => 'Tunisia', 'iso_code' => 'TN', 'interest_tax' => 19.00],
            ['name' => 'Israel', 'iso_code' => 'IL', 'interest_tax' => 17.00],
            ['name' => 'Brazil', 'iso_code' => 'BR', 'interest_tax' => 17.00],
            ['name' => 'Argentina', 'iso_code' => 'AR', 'interest_tax' => 21.00],
            ['name' => 'Chile', 'iso_code' => 'CL', 'interest_tax' => 19.00],
            ['name' => 'Peru', 'iso_code' => 'PE', 'interest_tax' => 18.00],
            ['name' => 'Colombia', 'iso_code' => 'CO', 'interest_tax' => 19.00],
            ['name' => 'Mexico', 'iso_code' => 'MX', 'interest_tax' => 16.00],
            ['name' => 'South Africa', 'iso_code' => 'ZA', 'interest_tax' => 15.00],
            ['name' => 'Kenya', 'iso_code' => 'KE', 'interest_tax' => 16.00],
            ['name' => 'Nigeria', 'iso_code' => 'NG', 'interest_tax' => 7.50],
            ['name' => 'Namibia', 'iso_code' => 'NA', 'interest_tax' => 15.00],
            ['name' => 'New Zealand', 'iso_code' => 'NZ', 'interest_tax' => 15.00],
            ['name' => 'South Korea', 'iso_code' => 'KR', 'interest_tax' => 10.00],
            ['name' => 'India', 'iso_code' => 'IN', 'interest_tax' => 18.00],
            ['name' => 'Sri Lanka', 'iso_code' => 'LK', 'interest_tax' => 18.00],
            ['name' => 'Malaysia', 'iso_code' => 'MY', 'interest_tax' => 10.00],
            ['name' => 'Tanzania', 'iso_code' => 'TZ', 'interest_tax' => 18.00],
            ['name' => 'Panama', 'iso_code' => 'PA', 'interest_tax' => 7.00],
        ];

        foreach ($extra as $row) {
            $iso = $row['iso_code'];
            if (Country::query()->where('iso_code', $iso)->exists()) {
                continue;
            }
            Country::query()->create([
                'name' => $row['name'],
                'iso_code' => $iso,
                'phone_code' => $phoneCodes[$iso] ?? '+0',
                'interest_tax' => $row['interest_tax'],
            ]);
        }
    }
}
