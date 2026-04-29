<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const el = ref(null);
const loadError = ref('');
let map = null;
/** @type {import('leaflet').Layer[]} */
const mapLayers = [];

onMounted(async () => {
    loadError.value = '';
    try {
        const L = (await import('leaflet')).default;
        await import('leaflet/dist/leaflet.css');

        if (!el.value) {
            return;
        }

        const { data } = await window.axios.get(route('rentals.map-data', {}, false));
        const ports = Array.isArray(data?.ports) ? data.ports : [];
        const routeEdges = Array.isArray(data?.route_edges) ? data.route_edges : [];
        const vesselPositions = Array.isArray(data?.vessel_positions) ? data.vessel_positions : [];
        const positions = Array.isArray(data?.positions) ? data.positions : [];

        // Rentals Center should focus on the user's active rental session(s),
        // so we only render fleet markers for shipments that include user's cargo.
        const fleetPositions = vesselPositions.filter((vp) => Boolean(vp?.is_user_shipment));

        const first = ports[0];
        const center = first ? [first.latitude, first.longitude] : [20, 0];

        map = L.map(el.value, { scrollWheelZoom: false }).setView(center, first ? 4 : 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        routeEdges.forEach((edge) => {
            const path = edge?.path;
            if (!Array.isArray(path) || path.length < 2) {
                return;
            }
            const latLngs = path.map(([lat, lng]) => [lat, lng]);
            const line = L.polyline(latLngs, {
                color: '#94a3b8',
                weight: 2,
                opacity: 0.75,
                dashArray: '6 8',
            }).addTo(map);
            mapLayers.push(line);
        });

        ports.forEach((p) => {
            const m = L.circleMarker([p.latitude, p.longitude], {
                radius: 6,
                color: '#0f172a',
                weight: 2,
                fillColor: '#94a3b8',
                fillOpacity: 0.9,
            }).addTo(map);
            m.bindPopup(`<strong>${p.name}</strong><br>${p.city ?? ''}`);
            mapLayers.push(m);
        });

        const fleetSorted = [...fleetPositions].sort((a, b) => {
            const ac = a.has_rental_cargo ? 1 : 0;
            const bc = b.has_rental_cargo ? 1 : 0;
            return ac - bc;
        });

        const round4 = (n) => Math.round(Number(n) * 10000) / 10000;
        const coordGroups = new Map();
        for (const vp of fleetSorted) {
            if (vp.latitude == null || vp.longitude == null) {
                continue;
            }
            const key = `${round4(vp.latitude)}_${round4(vp.longitude)}`;
            if (!coordGroups.has(key)) {
                coordGroups.set(key, []);
            }
            coordGroups.get(key).push(vp);
        }
        for (const g of coordGroups.values()) {
            if (g.length <= 1) {
                continue;
            }
            g.sort((a, b) => Number(a.vessel_id) - Number(b.vessel_id));
            const n = g.length;
            const r = 0.00052;
            g.forEach((vp, i) => {
                const ang = (2 * Math.PI * i) / n;
                vp._displayLat = vp.latitude + r * Math.sin(ang);
                vp._displayLng = vp.longitude + r * Math.cos(ang);
            });
        }

        const vesselPopupLines = (vp) => {
            const lines = [];
            const statusRaw = vp.shipment_status ? String(vp.shipment_status) : '';
            const statusLabel = statusRaw.replaceAll('_', ' ');
            if (vp.origin_name) {
                lines.push(`<span style="font-size:11px;color:#64748b">From: ${vp.origin_name}</span>`);
            }
            if (vp.destination_name) {
                lines.push(`<span style="font-size:11px;color:#64748b">To: ${vp.destination_name}</span>`);
            }
            if (statusLabel) {
                lines.push(`<span style="font-size:11px;color:#64748b">${statusLabel}</span>`);
            }
            const n = Number(vp.rental_cargo_count) || 0;
            if (vp.has_rental_cargo && n > 1) {
                lines.push(
                    `<span style="font-size:11px;color:#0d9488;font-weight:600">${n} rented containers on board</span>`
                );
            } else if (vp.has_rental_cargo) {
                lines.push(`<span style="font-size:11px;color:#0d9488;font-weight:600">Rented cargo on board</span>`);
            } else {
                lines.push(`<span style="font-size:11px;color:#64748b">No rented cargo on this leg</span>`);
            }
            if (vp.is_user_shipment) {
                lines.push(`<span style="font-size:11px;color:#2563eb;font-weight:600">Includes your rental</span>`);
            }
            return lines.join('<br>');
        };

        fleetSorted.forEach((vp) => {
            if (vp.latitude == null || vp.longitude == null) {
                return;
            }
            const plat = vp._displayLat ?? vp.latitude;
            const plng = vp._displayLng ?? vp.longitude;
            const hasRental = Boolean(vp.has_rental_cargo);
            const count = Number(vp.rental_cargo_count) || 0;
            const multiCargo = count > 1;
            const isMine = Boolean(vp.is_user_shipment);

            if (!hasRental) {
                const m = L.circleMarker([plat, plng], {
                    radius: 5,
                    color: '#64748b',
                    weight: 1.5,
                    fillColor: '#cbd5e1',
                    fillOpacity: 0.9,
                }).addTo(map);
                m.bindPopup(`<strong>${vp.vessel_name ?? 'Vessel'}</strong><br>${vesselPopupLines(vp)}`);
                mapLayers.push(m);
                return;
            }

            // Circle markers stay visible on all platforms; emoji divIcons often fail to render.
            const r = isMine ? (multiCargo ? 10 : 9) : multiCargo ? 8 : 7;
            const m = L.circleMarker([plat, plng], {
                radius: r,
                color: isMine ? '#1d4ed8' : '#0f766e',
                weight: isMine ? 3 : 2,
                fillColor: isMine ? '#93c5fd' : '#5eead4',
                fillOpacity: 0.92,
            }).addTo(map);
            m.bindPopup(
                `<strong>${isMine ? 'Your vessel' : 'Vessel'} — ${vp.vessel_name ?? 'Fleet'}</strong><br>${vesselPopupLines(vp)}`
            );
            mapLayers.push(m);
        });

        positions.forEach((pos) => {
            if (pos.latitude == null || pos.longitude == null) {
                return;
            }
            const m = L.circleMarker([pos.latitude, pos.longitude], {
                radius: 8,
                color: '#b45309',
                weight: 2.5,
                fillColor: '#fbbf24',
                fillOpacity: 0.95,
            }).addTo(map);
            const phase = pos.logistics_phase
                ? `<br><span style="font-size:11px;color:#64748b">${String(pos.logistics_phase)}</span>`
                : '';
            m.bindPopup(
                `<strong>Your container</strong><br>Rental #${pos.rental_id} · ${pos.container_serial ?? 'Container'}${phase}`
            );
            mapLayers.push(m);
        });

        if (mapLayers.length === 1) {
            const only = mapLayers[0];
            const center = typeof only.getLatLng === 'function' ? only.getLatLng() : only.getBounds().getCenter();
            map.setView(center, 6);
        } else if (mapLayers.length) {
            const group = L.featureGroup(mapLayers);
            map.fitBounds(group.getBounds().pad(0.15));
        }
    } catch (e) {
        loadError.value = 'Unable to load map data. Try again later.';
    }
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
    mapLayers.length = 0;
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Logistics</p>
                <h2 class="text-lg font-bold text-slate-900">Ports, routes & fleet</h2>
                <p class="mt-1 text-xs text-slate-600">
                    Ports (slate), your container leg (amber), your vessel on active shipments (blue). Gray dashed lines are open sea routes.
                </p>
            </div>
        </div>
        <p v-if="loadError" class="mb-2 text-sm text-rose-600">{{ loadError }}</p>
        <div ref="el" class="h-[22rem] w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-inner" />
    </div>
</template>

