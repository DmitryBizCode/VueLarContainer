#!/usr/bin/env node
/**
 * CLI wrapper around searoute-js that outputs JSON:
 * {
 *   "points": [[lat,lng], ...],
 *   "units": "km",
 *   "length": <number|null>
 * }
 *
 * Inputs are given as lat/lng (human-friendly), but searoute-js expects GeoJSON [lng,lat].
 */
/* eslint-disable no-console */

// NOTE: The repo root uses "type": "module", so we keep this file CommonJS via .cjs.

function parseArg(name) {
    const idx = process.argv.indexOf(`--${name}`);
    if (idx === -1) return null;
    const v = process.argv[idx + 1];
    if (v == null || v.startsWith('--')) return null;
    return v;
}

function num(v, label) {
    const n = Number(v);
    if (!Number.isFinite(n)) {
        throw new Error(`Invalid number for ${label}`);
    }
    return n;
}

function geoPoint(lat, lng) {
    return {
        type: 'Feature',
        properties: {},
        geometry: {
            type: 'Point',
            coordinates: [lng, lat],
        },
    };
}

async function main() {
    const originLat = parseArg('origin-lat');
    const originLng = parseArg('origin-lng');
    const destLat = parseArg('dest-lat');
    const destLng = parseArg('dest-lng');
    const units = parseArg('units') || 'km';
    const dropEndpoints = (parseArg('drop-endpoints') || '1') !== '0';

    if (originLat == null || originLng == null || destLat == null || destLng == null) {
        console.error(
            JSON.stringify({
                error:
                    'Usage: sea-path-cli.cjs --origin-lat <n> --origin-lng <n> --dest-lat <n> --dest-lng <n> [--units km|nm|miles|kilometers] [--drop-endpoints 1|0]',
            })
        );
        process.exit(2);
    }

    const oLat = num(originLat, 'origin-lat');
    const oLng = num(originLng, 'origin-lng');
    const dLat = num(destLat, 'dest-lat');
    const dLng = num(destLng, 'dest-lng');

    const searoute = require('searoute-js');

    // searoute-js currently logs debug info via console.log; silence it for clean JSON output.
    const origLog = console.log;
    console.log = () => {};
    try {
        const route = searoute(geoPoint(oLat, oLng), geoPoint(dLat, dLng), units);
        if (!route || !route.geometry || !Array.isArray(route.geometry.coordinates)) {
            console.error(JSON.stringify({ error: 'No route found' }));
            process.exit(3);
        }

        /** @type {Array<[number, number]>} */
        let coords = route.geometry.coordinates
            .filter((c) => Array.isArray(c) && c.length >= 2)
            .map(([lng, lat]) => [Number(lat), Number(lng)])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

        if (dropEndpoints && coords.length >= 2) {
            coords = coords.slice(1, -1);
        }

        const length = route.properties && typeof route.properties.length === 'number' ? route.properties.length : null;

        process.stdout.write(JSON.stringify({ points: coords, units, length }));
    } finally {
        console.log = origLog;
    }
}

main().catch((err) => {
    console.error(JSON.stringify({ error: err?.message || String(err) }));
    process.exit(1);
});

