<?php

namespace Database\Support;

/**
 * Canonical waypoint data for maritime route sea_path seeding (RouteSeeder).
 * Early patch, regional legs, and symmetric backfill differ where noted.
 */
final class SeaPathWaypointPatches
{
    /**
     * Port of Hamburg is stored on the inner Elbe; polylines must first reach open water (fairway / locks)
     * before crossing Schleswig-Holstein toward the Baltic or Kiel Canal.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function hamburgElbeToBrunsbuettel(): array
    {
        return [
            [53.76, 9.52],
            [53.88, 9.13],
        ];
    }

    /**
     * Nord-Ostsee-Kanal (Elbe locks → Kiel): approximate fairway through the cut — avoids chords over land.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsHamburgToKiel(): array
    {
        return array_merge(self::hamburgElbeToBrunsbuettel(), [
            [54.02, 9.38],
            [54.12, 9.62],
            [54.22, 9.88],
        ]);
    }

    /**
     * Hamburg → Gulf of Gdansk via Kieler Bucht and the western Baltic (no great-circle hop over Germany).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsHamburgToGdansk(): array
    {
        return array_merge(self::hamburgElbeToBrunsbuettel(), [
            [54.08, 9.92],
            [54.30, 10.55],
            [54.48, 11.25],
            [54.52, 12.70],
            [54.50, 14.05],
            [54.46, 15.40],
            [54.42, 17.00],
        ]);
    }

    /**
     * Gdansk → Szczecin through Pomeranian Bay and the Szczecin Lagoon (not over the lakeshore).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsGdanskToSzczecin(): array
    {
        return [
            [54.58, 17.90],
            [54.35, 16.20],
            [54.05, 14.85],
            [53.92, 14.38],
        ];
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsGdanskToKlaipeda(): array
    {
        return [
            [54.62, 18.95],
            [55.05, 19.95],
            [55.42, 20.65],
        ];
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsGdanskToGdynia(): array
    {
        return [[54.52, 18.52], [54.48, 18.58]];
    }

    /**
     * Kieler Bucht → Kattegat → Aarhus (prefix from inner Hamburg).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsHamburgToAarhus(): array
    {
        return array_merge(self::hamburgElbeToBrunsbuettel(), [
            [54.18, 9.78],
            [54.48, 10.05],
            [54.88, 10.22],
            [55.30, 10.28],
            [55.72, 10.29],
            [56.05, 10.28],
        ]);
    }

    /**
     * Fairway points on the lower Elbe before the inner Hamburg harbour coordinate.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function northSeaApproachToHamburgElbe(): array
    {
        return [[53.96, 9.05], [53.88, 9.28], [53.82, 9.45]];
    }

    /**
     * Skagerrak / North Sea → lower Elbe (Bergen is a fjord port; first points stay in offshore fairways).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsBergenToHamburg(): array
    {
        return array_merge([
            [60.12, 5.48],
            [59.35, 7.05],
            [58.25, 8.95],
            [57.20, 9.95],
            [56.15, 10.12],
            [55.05, 10.05],
            [54.18, 9.92],
        ], self::northSeaApproachToHamburgElbe());
    }

    /**
     * Seine Bay → western English Channel → west of Brittany into the Bay of Biscay.
     * Avoids straight chords across Normandy, Brittany, or the Cotentin.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function leHavreExitWestSouthwest(): array
    {
        return [
            [49.48, -0.38],
            [49.12, -2.70],
            [48.55, -4.70],
            [47.35, -6.95],
        ];
    }

    /**
     * Gironde → Bay of Biscay (north / west of Brittany) → Channel approaches to Le Havre.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsBordeauxToLeHavre(): array
    {
        return [
            [45.58, -1.08],
            [46.25, -2.85],
            [47.15, -4.35],
            [48.05, -5.45],
            [48.75, -5.60],
            [49.05, -4.85],
            [49.28, -3.40],
            [49.40, -1.85],
            [49.48, -0.35],
        ];
    }

    /**
     * Southern North Sea → Dover Strait → Seine Bay (no shortcut over the Picardy / Normandy coast).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsRotterdamToLeHavre(): array
    {
        return [
            [51.28, 3.05],
            [51.05, 2.20],
            [50.75, 1.05],
            [50.42, 0.05],
            [50.05, -0.55],
            [49.75, -0.42],
        ];
    }

    /**
     * Scheldt approaches → Channel fairway → Seine Bay.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsAntwerpToLeHavre(): array
    {
        return [
            [51.35, 2.95],
            [50.85, 1.45],
            [50.35, 0.15],
            [49.95, -0.85],
            [49.72, -0.55],
        ];
    }

    /**
     * Cross-Channel hop with a mid-point in the English Channel (avoids a chord over the Cotentin).
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsLeHavreToSouthampton(): array
    {
        return [
            [49.62, -0.45],
            [50.15, -0.95],
            [50.55, -1.25],
        ];
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsLeHavreToLeixoes(): array
    {
        return array_merge(self::leHavreExitWestSouthwest(), [
            [45.10, -9.40],
            [43.80, -10.50],
        ]);
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsLeHavreToSines(): array
    {
        return array_merge(self::leHavreExitWestSouthwest(), [
            [44.20, -9.10],
            [40.20, -11.50],
        ]);
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsLeHavreToLisbon(): array
    {
        return array_merge(self::leHavreExitWestSouthwest(), [
            [44.00, -9.50],
            [40.50, -10.80],
            [39.20, -11.80],
        ]);
    }

    /**
     * Atlantic / Gibraltar / Mediterranean chain — prefixed so the first segment does not cut inland France.
     *
     * @return list<array{0:float,1:float}>
     */
    private static function waypointsLeHavreToMarseille(): array
    {
        return array_merge(self::leHavreExitWestSouthwest(), [
            [46.50, -6.80], [44.50, -8.50], [43.00, -9.30],
            [40.50, -9.50], [37.95, -8.90], [36.40, -6.20], [36.00, -4.00],
            [36.50, -0.50], [38.50, 1.00], [40.50, 3.00], [42.80, 5.00],
        ]);
    }

    /**
     * @return list<array{0:string,1:string,2:list<array{0:float,1:float}>}>
     */
    public static function earlyPatchLegs(): array
    {
        return [
            // North Sea / Channel
            ['Port of Hamburg', 'Port of Rotterdam', [[54.18, 7.85], [53.65, 6.05]]],
            ['Port of Bordeaux', 'Port of Le Havre', self::waypointsBordeauxToLeHavre()],

            // Iberia / Bay of Biscay / Portuguese Atlantic
            ['Port of Bilbao', 'Port of Vigo', [[43.85, -4.15], [43.15, -6.05], [42.42, -7.85]]],
            ['Port of Vigo', 'Port of Bilbao', [[42.42, -7.85], [43.15, -6.05], [43.85, -4.15]]],
            ['Port of Vigo', 'Port of Leixoes', [[41.28, -8.85], [41.15, -8.78]]],
            ['Port of Leixoes', 'Port of Vigo', [[41.15, -8.78], [41.28, -8.85]]],
            // Valencia ↔ Bilbao must route via Gibraltar (great-circle crosses inland Spain).
            ['Port of Valencia', 'Port of Bilbao', [[38.60, -0.70], [36.05, -5.65], [37.40, -9.60], [42.60, -9.60], [43.75, -5.50]]],
            ['Port of Bilbao', 'Port of Valencia', [[43.75, -5.50], [42.60, -9.60], [37.40, -9.60], [36.05, -5.65], [38.60, -0.70]]],
            ['Port of Valencia', 'Port of Algeciras', [[38.35, -0.55], [37.10, -2.20], [36.45, -4.20], [36.18, -5.35]]],
            ['Port of Algeciras', 'Port of Valencia', [[36.18, -5.35], [36.45, -4.20], [37.10, -2.20], [38.35, -0.55]]],
            ['Port of Algeciras', 'Port of Sines', [[36.22, -6.85], [37.15, -8.55], [37.88, -8.92]]],
            ['Port of Sines', 'Port of Algeciras', [[37.88, -8.92], [37.15, -8.55], [36.22, -6.85]]],
            ['Port of Barcelona', 'Port of Marseille', [[41.05, 2.85], [42.10, 4.25], [42.85, 5.05]]],
            ['Port of Marseille', 'Port of Barcelona', [[42.85, 5.05], [42.10, 4.25], [41.05, 2.85]]],
            // Barcelona ↔ Genoa: avoid routing across southern France (great-circle / searoute artefact).
            ['Port of Barcelona', 'Port of Genoa', [[41.35, 3.05], [42.10, 5.35], [43.05, 7.15], [43.85, 8.55]]],
            ['Port of Genoa', 'Port of Barcelona', [[43.85, 8.55], [43.05, 7.15], [42.10, 5.35], [41.35, 3.05]]],

            // Eastern Atlantic long legs (kept west of continental shelf; reverse from searouteOverrideByPortPair)
            ['Port of Le Havre', 'Port of Leixoes', self::waypointsLeHavreToLeixoes()],
            ['Port of Le Havre', 'Port of Sines', self::waypointsLeHavreToSines()],
            ['Port of Le Havre', 'Port of Lisbon', self::waypointsLeHavreToLisbon()],

            // Le Havre ↔ Marseille via Atlantic / Gibraltar / Mediterranean
            ['Port of Le Havre', 'Port of Marseille', self::waypointsLeHavreToMarseille()],

            // Hamburg ↔ Gdansk (reverse applied by overlay)
            ['Port of Hamburg', 'Port of Gdansk', self::waypointsHamburgToGdansk()],

            // Baltic feeder legs (coordinates kept on known fairways; same geometry as symmetric backfill).
            ['Port of Gdansk', 'Port of Szczecin', self::waypointsGdanskToSzczecin()],
            ['Port of Gdansk', 'Port of Klaipeda', self::waypointsGdanskToKlaipeda()],
            ['Port of Klaipeda', 'Port of Riga', [[56.50, 20.40], [57.00, 23.70]]],
            ['Port of Riga', 'Port of Klaipeda', [[57.00, 23.70], [56.50, 20.40]]],
        ];
    }

    /**
     * Regional legs applied by RouteSeeder (order matters for duplicate route keys).
     *
     * @return list<array{0:string,1:string,2:list<array{0:float,1:float}>}>
     */
    public static function routeSeederRegionalLegs(): array
    {
        return [
            ['Port of Hamburg', 'Port of Rotterdam', [[54.18, 7.85], [53.65, 6.05]]],
            // Channel / Bay of Biscay: dense offshore points so densified segments stay seaward of FR / UK coasts.
            ['Port of Rotterdam', 'Port of Le Havre', self::waypointsRotterdamToLeHavre()],
            ['Port of Le Havre', 'Port of Rotterdam', array_reverse(self::waypointsRotterdamToLeHavre())],
            ['Port of Le Havre', 'Port of Leixoes', self::waypointsLeHavreToLeixoes()],
            ['Port of Leixoes', 'Port of Le Havre', array_reverse(self::waypointsLeHavreToLeixoes())],
            // Bordeaux is an estuary port; bend seaward for a clean visualization.
            ['Port of Bordeaux', 'Port of Le Havre', self::waypointsBordeauxToLeHavre()],
            ['Port of Le Havre', 'Port of Bordeaux', array_reverse(self::waypointsBordeauxToLeHavre())],
            // English Channel / southern North Sea (replaces Dunkirk hop; Antwerp has no river shortcut to Le Havre).
            ['Port of Antwerp', 'Port of Le Havre', self::waypointsAntwerpToLeHavre()],
            ['Port of Le Havre', 'Port of Antwerp', array_reverse(self::waypointsAntwerpToLeHavre())],
            ['Port of Le Havre', 'Port of Southampton', self::waypointsLeHavreToSouthampton()],
            ['Port of Southampton', 'Port of Le Havre', array_reverse(self::waypointsLeHavreToSouthampton())],

            // Iberia / Bay of Biscay / Portuguese Atlantic: avoid great-circle cuts over land.
            ['Port of Bilbao', 'Port of Vigo', [[43.85, -4.15], [43.15, -6.05], [42.42, -7.85]]],
            ['Port of Vigo', 'Port of Bilbao', [[42.42, -7.85], [43.15, -6.05], [43.85, -4.15]]],
            ['Port of Vigo', 'Port of Leixoes', [[41.28, -8.85], [41.15, -8.78]]],
            ['Port of Leixoes', 'Port of Vigo', [[41.15, -8.78], [41.28, -8.85]]],
            // Valencia ↔ Bilbao must route via Gibraltar (great-circle crosses inland Spain).
            ['Port of Valencia', 'Port of Bilbao', [[38.60, -0.70], [36.05, -5.65], [37.40, -9.60], [42.60, -9.60], [43.75, -5.50]]],
            ['Port of Bilbao', 'Port of Valencia', [[43.75, -5.50], [42.60, -9.60], [37.40, -9.60], [36.05, -5.65], [38.60, -0.70]]],
            ['Port of Valencia', 'Port of Algeciras', [[38.35, -0.55], [37.10, -2.20], [36.45, -4.20], [36.18, -5.35]]],
            ['Port of Algeciras', 'Port of Valencia', [[36.18, -5.35], [36.45, -4.20], [37.10, -2.20], [38.35, -0.55]]],
            // West of Cape St. Vincent, then along Portuguese coast (not over southern PT).
            ['Port of Algeciras', 'Port of Sines', [[36.22, -6.85], [37.15, -8.55], [37.88, -8.92]]],
            ['Port of Sines', 'Port of Algeciras', [[37.88, -8.92], [37.15, -8.55], [36.22, -6.85]]],
            ['Port of Barcelona', 'Port of Marseille', [[41.05, 2.85], [42.10, 4.25], [42.85, 5.05]]],
            ['Port of Marseille', 'Port of Barcelona', [[42.85, 5.05], [42.10, 4.25], [41.05, 2.85]]],
            ['Port of Barcelona', 'Port of Genoa', [[41.35, 3.05], [42.10, 5.35], [43.05, 7.15], [43.85, 8.55]]],
            ['Port of Genoa', 'Port of Barcelona', [[43.85, 8.55], [43.05, 7.15], [42.10, 5.35], [41.35, 3.05]]],
            // Long eastern Atlantic legs: stay west of the continental shelf bend.
            ['Port of Le Havre', 'Port of Sines', self::waypointsLeHavreToSines()],
            ['Port of Sines', 'Port of Le Havre', array_reverse(self::waypointsLeHavreToSines())],
            ['Port of Le Havre', 'Port of Lisbon', self::waypointsLeHavreToLisbon()],
            ['Port of Lisbon', 'Port of Le Havre', array_reverse(self::waypointsLeHavreToLisbon())],

            // Le Havre ↔ Marseille: great-circle crosses France, so route via Atlantic/Gibraltar/Mediterranean.
            ['Port of Le Havre', 'Port of Marseille', self::waypointsLeHavreToMarseille()],
            ['Port of Marseille', 'Port of Le Havre', array_reverse(self::waypointsLeHavreToMarseille())],

            // Hamburg ↔ Gdansk (reverse applied by overlay)
            ['Port of Hamburg', 'Port of Gdansk', self::waypointsHamburgToGdansk()],

            // Elbe locks → Kiel Canal (overwrites on each seed — see RouteSeeder::applyRegionalSeaPaths).
            ['Port of Hamburg', 'Port of Kiel', self::waypointsHamburgToKiel()],

            // Baltic feeder legs (coordinates kept on known fairways; same geometry as symmetric backfill).
            ['Port of Gdansk', 'Port of Szczecin', self::waypointsGdanskToSzczecin()],
            ['Port of Gdansk', 'Port of Klaipeda', self::waypointsGdanskToKlaipeda()],
            ['Port of Gdansk', 'Port of Gdynia', self::waypointsGdanskToGdynia()],
            ['Port of Klaipeda', 'Port of Riga', [[56.50, 20.40], [57.00, 23.70]]],
            ['Port of Riga', 'Port of Klaipeda', [[57.00, 23.70], [56.50, 20.40]]],

            // Gdansk ↔ Rotterdam: fairways through Great Belt / Kattegat, clear N of Skagen, then North Sea.
            // (Sparse chords here read as overland on Mercator if not densified in map geometry.)
            ['Port of Gdansk', 'Port of Rotterdam', [
                [54.52, 17.90],
                [54.64, 15.55],
                [54.78, 13.40],
                [55.10, 11.50],
                [55.45, 11.05],
                [56.02, 11.05],
                [56.88, 10.80],
                [57.55, 10.40],
                [57.32, 9.15],
                [56.45, 7.30],
                [54.80, 6.00],
                [52.40, 4.70],
            ]],
            ['Port of Rotterdam', 'Port of Gdansk', [
                [52.40, 4.70],
                [54.80, 6.00],
                [56.45, 7.30],
                [57.32, 9.15],
                [57.55, 10.40],
                [56.88, 10.80],
                [56.02, 11.05],
                [55.45, 11.05],
                [55.10, 11.50],
                [54.78, 13.40],
                [54.64, 15.55],
                [54.52, 17.90],
            ]],

            // UK / Irish Sea / Norway / Adriatic feeder hubs (see PortSeeder + RouteSeeder::europeDemoEdges).
            ['Port of Felixstowe', 'Port of Southampton', [[51.22, 1.38], [51.02, 0.12], [50.92, -0.88]]],
            ['Port of Felixstowe', 'Port of Rotterdam', [[52.48, 3.12], [52.08, 4.12]]],
            ['Port of Dublin', 'Port of Southampton', [[52.18, -5.42], [51.12, -4.22], [50.94, -1.52]]],
            ['Port of Dublin', 'Port of Rotterdam', [
                [53.52, -5.18], [53.18, -3.35], [52.78, 1.15], [52.05, 4.02],
            ]],
            ['Port of Bergen', 'Port of Gothenburg', [[59.28, 9.92], [58.48, 10.78], [57.72, 11.42]]],
            ['Port of Bergen', 'Port of Hamburg', self::waypointsBergenToHamburg()],
            ['Port of Rijeka', 'Port of Koper', [[45.38, 14.08], [45.43, 13.90]]],
            ['Port of Rijeka', 'Port of Venice', [[45.50, 13.52], [45.44, 12.92], [45.44, 12.48]]],

            // Indian Ocean: searoute-js often has no graph path (HTTP 404) for these open-ocean legs.
            ['Port of Colombo', 'Port of Singapore', [[5.50, 88.50], [4.00, 93.50], [2.50, 98.50]]],
            ['Port of Singapore', 'Port of Colombo', [[2.50, 98.50], [4.00, 93.50], [5.50, 88.50]]],
            ['Port of Colombo', 'Port of Mombasa', [[3.00, 72.00], [-1.00, 58.00], [-3.80, 42.00]]],
            ['Port of Mombasa', 'Port of Colombo', [[-3.80, 42.00], [-1.00, 58.00], [3.00, 72.00]]],
        ];
    }

    /**
     * @return list<array{0:string,1:string,2:list<array{0:float,1:float}>}>
     */
    public static function symmetricBackfillLegs(): array
    {
        return [
            ['Port of Rotterdam', 'Port of Antwerp', [[51.80, 3.50], [51.45, 3.55]]],
            ['Port of Antwerp', 'Port of Zeebrugge', [[51.35, 3.50], [51.32, 3.20]]],
            ['Port of Rotterdam', 'Port of Amsterdam', [[52.10, 4.10], [52.40, 4.35]]],
            ['Port of Le Havre', 'Port of Southampton', self::waypointsLeHavreToSouthampton()],
            ['Port of Zeebrugge', 'Port of Rotterdam', [[51.50, 3.30], [51.80, 3.80]]],
            ['Port of Hamburg', 'Port of Bremerhaven', [[53.85, 8.45]]],
            ['Port of Bremerhaven', 'Port of Wilhelmshaven', [[53.75, 8.10]]],
            ['Port of Wilhelmshaven', 'Port of Rotterdam', [[53.50, 6.50], [52.80, 4.40]]],
            ['Port of Hamburg', 'Port of Kiel', self::waypointsHamburgToKiel()],
            ['Port of Kiel', 'Port of Aarhus', [[54.72, 10.45], [55.20, 10.45], [55.70, 10.38], [56.00, 10.32]]],
            ['Port of Rotterdam', 'Port of Hamburg', array_merge(
                [[53.65, 6.05], [54.18, 7.85]],
                self::northSeaApproachToHamburgElbe()
            )],
            ['Port of Antwerp', 'Port of Hamburg', array_merge(
                [[51.80, 3.20], [53.40, 5.50], [54.00, 8.00]],
                self::northSeaApproachToHamburgElbe()
            )],

            // --- Atlantic Europe ---
            ['Port of Rotterdam', 'Port of Le Havre', self::waypointsRotterdamToLeHavre()],
            ['Port of Bordeaux', 'Port of Le Havre', self::waypointsBordeauxToLeHavre()],
            ['Port of Le Havre', 'Port of Lisbon', self::waypointsLeHavreToLisbon()],
            ['Port of Le Havre', 'Port of Leixoes', self::waypointsLeHavreToLeixoes()],
            ['Port of Le Havre', 'Port of Sines', self::waypointsLeHavreToSines()],

            // --- Iberia / Bay of Biscay / Portugal ---
            ['Port of Vigo', 'Port of Leixoes', [[41.28, -8.85], [41.15, -8.78]]],
            ['Port of Vigo', 'Port of Bilbao', [[42.42, -7.85], [43.15, -6.05], [43.85, -4.15]]],
            // Valencia ↔ Bilbao must route via Gibraltar (great-circle crosses inland Spain).
            ['Port of Valencia', 'Port of Bilbao', [[38.60, -0.70], [36.05, -5.65], [37.40, -9.60], [42.60, -9.60], [43.75, -5.50]]],
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
            ['Port of Barcelona', 'Port of Genoa', [[41.35, 3.05], [42.10, 5.35], [43.05, 7.15], [43.85, 8.55]]],
            ['Port of Genoa', 'Port of Barcelona', [[43.85, 8.55], [43.05, 7.15], [42.10, 5.35], [41.35, 3.05]]],
            ['Port of Le Havre', 'Port of Marseille', self::waypointsLeHavreToMarseille()],

            // --- Gibraltar / North Africa ---
            ['Port of Algeciras', 'Tanger Med', [[35.95, -5.40]]],
            ['Tanger Med', 'Port of Casablanca', [[35.30, -6.20], [33.70, -7.70]]],
            ['Port of Marseille', 'Port of Algiers', [[42.00, 4.80], [38.50, 3.80], [36.90, 3.20]]],
            ['Port of Marseille', 'Port of Rades', [[42.00, 5.50], [38.50, 8.00], [36.80, 10.30]]],

            // --- Central / Eastern Med ---
            // Genoa → Koper: great-circle crosses the Italian peninsula.
            // Route south via Tyrrhenian Sea, Strait of Messina, and north up the Adriatic.
            ['Port of Genoa', 'Port of Koper', [
                [44.00, 9.65],   // Ligurian Sea, off La Spezia
                [43.30, 9.80],   // Tyrrhenian, west of Livorno
                [41.50, 11.20],  // Tyrrhenian, west of Civitavecchia
                [40.50, 13.50],  // Tyrrhenian, south of Naples
                [38.20, 15.55],  // Strait of Messina
                [38.50, 16.20],  // Ionian Sea
                [40.20, 18.30],  // Strait of Otranto
                [43.40, 15.00],  // Mid-Adriatic
                [45.00, 13.80],  // Northern Adriatic, near Pula
            ]],
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
            // Piraeus → Constanta: must transit Dardanelles and Bosphorus — not overland Thrace.
            ['Port of Piraeus', 'Port of Constanta', [
                [38.80, 24.80],  // Southern Aegean
                [40.35, 26.10],  // Northern Aegean, Hellespont approach
                [40.25, 26.50],  // Dardanelles (Çanakkale) entrance
                [40.65, 27.40],  // Sea of Marmara
                [41.00, 28.85],  // Bosphorus exit (north end)
                [41.80, 29.00],  // Black Sea south coast
                [43.30, 29.60],  // Black Sea, heading NW
                [44.00, 28.80],  // Near Constanta
            ]],
            ['Port of Piraeus', 'Port of Thessaloniki', [[38.60, 24.20], [40.00, 23.50]]],
            ['Port of Thessaloniki', 'Port of Istanbul Ambarli', [[40.40, 24.50], [40.70, 27.50]]],
            // Thessaloniki → Constanta: must transit Dardanelles and Bosphorus — not overland Thrace.
            ['Port of Thessaloniki', 'Port of Constanta', [
                [40.60, 24.20],  // Thermaic Gulf / northern Aegean
                [40.35, 25.80],  // Aegean, Dardanelles approach
                [40.25, 26.50],  // Dardanelles entrance
                [40.65, 27.40],  // Sea of Marmara
                [41.00, 28.85],  // Bosphorus
                [41.80, 29.00],  // Black Sea south coast
                [43.30, 29.60],  // Black Sea, heading NW
                [44.00, 28.80],  // Near Constanta
            ]],
            ['Port of Odesa', 'Port of Istanbul Ambarli', [[45.50, 30.50], [43.50, 32.00], [41.70, 29.50]]],

            // --- Long Med ↔ North Europe ---
            ['Port of Odesa', 'Port of Rotterdam', [
                [45.20, 30.80], [41.60, 29.00], [38.60, 24.80], [37.20, 20.00], [36.50, 11.80],
                [36.00, 4.00], [36.00, -1.50], [36.00, -6.00], [38.00, -9.40], [43.00, -9.50],
                [47.50, -4.80], [50.40, 0.40], [51.85, 3.90],
            ]],
            ['Port of Odesa', 'Port of Hamburg', array_merge([
                [45.20, 30.80], [41.60, 29.00], [38.60, 24.80], [37.20, 20.00], [36.50, 11.80],
                [36.00, 4.00], [36.00, -1.50], [36.00, -6.00], [38.00, -9.40], [43.00, -9.50],
                [47.80, -4.60], [50.50, 1.40], [53.00, 5.00], [54.00, 8.00],
            ], self::northSeaApproachToHamburgElbe())],
            // Gdansk ↔ Rotterdam: same geometry as routeSeederRegionalLegs (overlay wins on duplicate keys).
            ['Port of Gdansk', 'Port of Rotterdam', [
                [54.52, 17.90],
                [54.64, 15.55],
                [54.78, 13.40],
                [55.10, 11.50],
                [55.45, 11.05],
                [56.02, 11.05],
                [56.88, 10.80],
                [57.55, 10.40],
                [57.32, 9.15],
                [56.45, 7.30],
                [54.80, 6.00],
                [52.40, 4.70],
            ]],

            // --- Baltic ---
            ['Port of Hamburg', 'Port of Gdansk', self::waypointsHamburgToGdansk()],
            ['Port of Gdansk', 'Port of Gdynia', self::waypointsGdanskToGdynia()],
            ['Port of Gdansk', 'Port of Szczecin', self::waypointsGdanskToSzczecin()],
            ['Port of Gdansk', 'Port of Klaipeda', self::waypointsGdanskToKlaipeda()],
            ['Port of Klaipeda', 'Port of Riga', [[56.50, 20.40], [57.00, 23.70]]],
            ['Port of Riga', 'Port of Tallinn', [[58.00, 23.50], [59.20, 24.30]]],
            ['Port of Tallinn', 'Port of Helsinki', [[59.60, 24.70]]],
            ['Port of Helsinki', 'Port of Stockholm', [[59.70, 22.80], [59.50, 19.50]]],
            ['Port of Stockholm', 'Port of Tallinn', [[59.20, 21.00], [59.30, 23.80]]],
            ['Port of Gothenburg', 'Port of Helsinki', [
                [57.25, 12.05], [57.00, 13.20], [55.80, 13.60], [55.35, 15.10], [57.20, 19.00], [59.50, 23.80],
            ]],
            ['Port of Copenhagen', 'Port of Gothenburg', [
                [55.92, 12.58], [56.15, 12.10], [56.55, 12.05], [57.05, 12.00], [57.45, 11.95],
            ]],
            // Øresund → Kattegat (avoid rhumb cuts across Zealand).
            ['Port of Copenhagen', 'Port of Aarhus', [
                [55.92, 12.58], [56.05, 12.05], [56.12, 11.45], [56.15, 10.95],
            ]],
            ['Port of Oslo', 'Port of Gothenburg', [[59.25, 10.72], [58.55, 10.95], [58.05, 11.35], [57.45, 11.65]]],
            ['Port of Oslo', 'Port of Copenhagen', [
                [59.25, 10.72], [58.75, 10.68], [58.15, 10.78], [57.45, 10.95],
                [56.85, 11.25], [56.15, 11.65], [55.92, 12.35],
            ]],
            ['Port of Aarhus', 'Port of Gothenburg', [[56.35, 10.95], [56.85, 11.15], [57.25, 11.55]]],
            ['Port of Hamburg', 'Port of Aarhus', self::waypointsHamburgToAarhus()],

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

    /**
     * Canonical mid-point waypoints for legs where searoute-js may route across land
     * near complex coastlines. Applied after HTTP searoute in {@see \App\Console\Commands\RoutesBuildSeaPathCommand}
     * so `routes:build-sea-path --force` does not replace good seed geometry with bad polylines.
     *
     * Later overlay sources win on duplicate port pairs (regional over early, etc.).
     *
     * @return array<string, list<array{0:float,1:float}>>
     */
    public static function searouteOverrideByPortPair(): array
    {
        $map = [];
        $overlay = function (array $legs) use (&$map): void {
            foreach ($legs as [$from, $to, $wp]) {
                $wp = array_values($wp);
                $map[$from.'|'.$to] = $wp;
                $rev = array_reverse(array_map(
                    static fn (array $p): array => [$p[0], $p[1]],
                    $wp
                ));
                $map[$to.'|'.$from] = array_values($rev);
            }
        };

        $overlay(self::symmetricBackfillLegs());
        $overlay(self::earlyPatchLegs());
        $overlay(self::routeSeederRegionalLegs());

        return $map;
    }
}
