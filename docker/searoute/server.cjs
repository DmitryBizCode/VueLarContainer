#!/usr/bin/env node
/* eslint-disable no-console */

// NOTE: The repo root uses "type": "module", so we keep this file CommonJS via .cjs.
const express = require('express');

function parseJsonBody(req, res, next) {
    let data = '';
    req.on('data', (chunk) => {
        data += chunk;
        if (data.length > 1_000_000) {
            res.status(413).json({ error: 'Payload too large' });
            req.destroy();
        }
    });
    req.on('end', () => {
        if (!data) {
            req.body = {};
            return next();
        }
        try {
            req.body = JSON.parse(data);
            return next();
        } catch {
            return res.status(400).json({ error: 'Invalid JSON' });
        }
    });
}

function num(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}

function geoPoint(lat, lng) {
    return {
        type: 'Feature',
        properties: {},
        geometry: { type: 'Point', coordinates: [lng, lat] },
    };
}

const app = express();
app.disable('x-powered-by');
app.use(parseJsonBody);

app.get('/health', (_req, res) => res.json({ ok: true }));

app.post('/route', (req, res) => {
    const origin = req.body?.origin ?? {};
    const destination = req.body?.destination ?? {};
    const units = typeof req.body?.units === 'string' ? req.body.units : 'kilometers';
    const dropEndpoints = req.body?.drop_endpoints !== false;

    const oLat = num(origin.lat);
    const oLng = num(origin.lng);
    const dLat = num(destination.lat);
    const dLng = num(destination.lng);

    if (oLat == null || oLng == null || dLat == null || dLng == null) {
        return res.status(422).json({
            error: 'origin/destination must include numeric lat/lng',
        });
    }

    const searoute = require('searoute-js');

    // Silence searoute-js debug logs.
    const origLog = console.log;
    console.log = () => {};
    try {
        const route = searoute(geoPoint(oLat, oLng), geoPoint(dLat, dLng), units);
        if (!route || !route.geometry || !Array.isArray(route.geometry.coordinates)) {
            return res.status(404).json({ error: 'No route found' });
        }

        /** @type {Array<[number, number]>} */
        let points = route.geometry.coordinates
            .filter((c) => Array.isArray(c) && c.length >= 2)
            .map(([lng, lat]) => [Number(lat), Number(lng)])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

        if (dropEndpoints && points.length >= 2) {
            points = points.slice(1, -1);
        }

        const length = route.properties && typeof route.properties.length === 'number' ? route.properties.length : null;

        return res.json({ points, units, length });
    } catch (e) {
        return res.status(500).json({ error: e?.message || 'Unexpected error' });
    } finally {
        console.log = origLog;
    }
});

const port = Number(process.env.PORT || 3001);
app.listen(port, () => {
    console.log(`searoute server listening on :${port}`);
});

