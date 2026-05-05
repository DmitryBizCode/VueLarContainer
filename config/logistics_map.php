<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Imminent rental starts on the map
    |--------------------------------------------------------------------------
    |
    | Include rentals whose start_date is up to this many days in the future
    | (still subject to end_date >= now). They render at the origin leg (t = 0).
    |
    */
    'imminent_start_horizon_days' => 60,

    /*
    |--------------------------------------------------------------------------
    | Rentals center logistics map: base ports shown on the map
    |--------------------------------------------------------------------------
    |
    | Keep the map readable by showing only the main European ports (2–3 per
    | country) and the routes between them. Anything outside this list is
    | filtered out from /rentals/map-data.
    |
    | Names must match `ports.name` exactly.
    |
    */
    'port_names' => [
        // NL
        'Port of Rotterdam',
        'Port of Amsterdam',

        // BE
        'Port of Antwerp',
        'Port of Zeebrugge',

        // DE
        'Port of Hamburg',
        'Port of Bremerhaven',
        'Port of Wilhelmshaven',
        'Port of Kiel',

        // FR
        'Port of Le Havre',
        'Port of Marseille',
        'Port of Bordeaux',

        // ES
        'Port of Barcelona',
        'Port of Valencia',
        'Port of Bilbao',
        'Port of Algeciras',
        'Port of Vigo',

        // IT
        'Port of Genoa',
        'Port of Trieste',
        'Port of La Spezia',
        'Port of Livorno',
        'Port of Gioia Tauro',
        'Port of Venice',
        'Port of Ravenna',
        'Port of Naples',
        'Port of Taranto',

        // PT
        'Port of Sines',
        'Port of Lisbon',
        'Port of Leixoes',

        // SI / Adriatic
        'Port of Koper',

        // GR / Aegean
        'Port of Piraeus',
        'Port of Thessaloniki',
        'Malta Freeport',
        'Port of Limassol',

        // GB / IE
        'Port of Southampton',

        // Nordics / Baltics
        'Port of Aarhus',
        'Port of Copenhagen',
        'Port of Gothenburg',
        'Port of Oslo',
        'Port of Stockholm',
        'Port of Helsinki',
        'Port of Tallinn',
        'Port of Riga',
        'Port of Klaipeda',

        // PL
        'Port of Gdansk',
        'Port of Gdynia',
        'Port of Szczecin',

        // Black Sea (EU periphery)
        'Port of Constanta',
        'Port of Odesa',

        // Turkey (Bosporus / Marmara)
        'Port of Istanbul Ambarli',

        // Mediterranean / Suez gateway (often used in demo routes)
        'Port of Port Said',

        // Egypt (more North Africa)
        'Port of Alexandria',
        'Port of Damietta',

        // UAE (Middle East)
        'Port of Jebel Ali',
        'Khalifa Port',

        // North Africa / Levant
        'Tanger Med',
        'Port of Casablanca',
        'Port of Algiers',
        'Port of Rades',
        'Port of Haifa',

        // US / East Asia (Pacific demo lanes)
        'Port of Los Angeles',
        'Port of New York',
        'Port of Seattle',
        'Port of Miami',
        'Port of Savannah',
        'Port of Shanghai',
        'Port of Singapore',
        'Port of Yokohama',
        'Port of Busan',
        'Port of Shekou',

        // Americas (UNLOCODE-aligned seed ports)
        'Port of Vancouver',
        'Port of Callao',
        'Port of Cartagena',
        'Port of Veracruz',
        'Port of Santos',
        'Port of Buenos Aires',
        'Port of Colon',

        // Indian Ocean / Southeast Asia (seed ports)
        'Port of Mumbai',
        'Port of Colombo',
        'Port of Klang',

        // Sub-Saharan Africa / Atlantic (seed ports)
        'Port of Durban',
        'Port of Mombasa',
        'Port of Dar es Salaam',
        'Port of Lagos',
        'Port of Walvis Bay',

        // Oceania (seed ports)
        'Port of Adelaide',
        'Port of Auckland',
    ],
];
