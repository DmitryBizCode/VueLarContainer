<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const el = ref(null);
const loadError = ref('');
let map = null;
/** @type {import('leaflet').Layer[]} */
const mapLayers = [];

/**
 * Inline SVG icon factory. Every marker is a compact circle badge with an inline SVG glyph.
 * `rotation` rotates the SVG (not the circle) — used to orient vessel icons by heading.
 */
const svgIcon = (glyph, { bg = '#0f172a', ring = '#ffffff', fg = '#ffffff', size = 28, rotation = 0 } = {}) => {
    const svgStyle = rotation ? `style="transform:rotate(${rotation}deg)"` : '';
    const html = `
        <div style="
            width:${size}px;height:${size}px;border-radius:9999px;background:${bg};
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 1px 3px rgba(15,23,42,0.35),0 0 0 2px ${ring};
            color:${fg};
        ">
            <svg xmlns="http://www.w3.org/2000/svg" width="${Math.round(size * 0.6)}" height="${Math.round(size * 0.6)}"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" ${svgStyle}>${glyph}</svg>
        </div>`;
    return { html, size };
};

// --- Glyphs ---
const GLYPHS = {
    port: '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    origin: '<path d="M5 3v18"/><path d="M5 4h12l-2 4 2 4H5"/>',
    destination: '<path d="M12 2a6 6 0 0 0-6 6c0 5 6 12 6 12s6-7 6-12a6 6 0 0 0-6-6z"/><circle cx="12" cy="8" r="2"/>',
    transfer: '<path d="M17 3l4 4-4 4"/><path d="M3 7h18"/><path d="M7 21l-4-4 4-4"/><path d="M21 17H3"/>',
    transfer_completed: '<path d="M17 3l4 4-4 4"/><path d="M3 7h18"/><path d="M7 21l-4-4 4-4"/><path d="M21 17H3"/><path d="M20 6L9 17l-5-5"/>',
    multi: '<path d="M7 7h10v10H7z"/><path d="M9 3h6v4H9z"/><path d="M9 17h6v4H9z"/><path d="M3 9h4v6H3z"/><path d="M17 9h4v6h-4z"/>',
    container: '<rect x="3" y="7" width="18" height="10" rx="1"/><path d="M7 7v10M11 7v10M15 7v10"/>',
    // Top-down directional arrow: bow at top (north). Rotate by heading degrees to orient.
    vessel: '<path d="M12 2l5 15-5-2.5-5 2.5z" fill="currentColor" stroke="none"/>',
    unloading: '<path d="M12 3v10"/><path d="M8 9l4 4 4-4"/><path d="M4 17h16"/><path d="M4 21h16"/>',
    delivered: '<path d="M20 6L9 17l-5-5"/>',
};

const ICON_STYLES = {
    port: { glyph: GLYPHS.port, bg: '#475569', fg: '#f8fafc', size: 20 },
    origin: { glyph: GLYPHS.origin, bg: '#2563eb', fg: '#ffffff', size: 30 },
    destination: { glyph: GLYPHS.destination, bg: '#dc2626', fg: '#ffffff', size: 30 },
    transfer: { glyph: GLYPHS.transfer, bg: '#f59e0b', fg: '#ffffff', size: 28 },
    transfer_completed: { glyph: GLYPHS.transfer_completed, bg: '#64748b', fg: '#ffffff', size: 28 },
    multi: { glyph: GLYPHS.multi, bg: '#0f172a', fg: '#ffffff', size: 30 },
    container: { glyph: GLYPHS.container, bg: '#b45309', fg: '#ffffff', size: 30 },
    vessel_user: { glyph: GLYPHS.vessel, bg: '#1d4ed8', fg: '#ffffff', size: 34 },
    vessel_fleet: { glyph: GLYPHS.vessel, bg: '#0f766e', fg: '#ffffff', size: 28 },
    vessel_idle: { glyph: GLYPHS.vessel, bg: '#64748b', fg: '#ffffff', size: 22 },
    unloading: { glyph: GLYPHS.unloading, bg: '#0d9488', fg: '#ffffff', size: 30 },
    delivered: { glyph: GLYPHS.delivered, bg: '#16a34a', fg: '#ffffff', size: 28 },
};

/** @param {number} heading Compass bearing 0–359 (0 = north, clockwise). Only applied to vessel* kinds. */
const buildLeafletIcon = (L, kind, { heading = 0 } = {}) => {
    const s = ICON_STYLES[kind] || ICON_STYLES.port;
    const rotation = kind.startsWith('vessel') ? heading : 0;
    const { html, size } = svgIcon(s.glyph, { bg: s.bg, fg: s.fg, size: s.size, rotation });
    return L.divIcon({
        html,
        className: 'logistics-map-icon',
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
        popupAnchor: [0, -size / 2],
    });
};

const fmtDate = (v) => {
    if (!v) return null;
    try {
        return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(v));
    } catch {
        return String(v);
    }
};

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
        const rentalRouteSegments = Array.isArray(data?.rental_route_segments) ? data.rental_route_segments : [];

        // User-only: show vessels only while actually at sea.
        const fleetPositions = vesselPositions.filter((vp) => {
            const st = String(vp?.shipment_status || '').toLowerCase();
            return ['in_transit', 'in_progress'].includes(st);
        });

        // Per-port roles for icon selection.
        /** @type {Map<string, Set<string>>} */
        const portRoles = new Map();
        const addRole = (id, role) => {
            if (id == null) return;
            const key = String(id);
            if (!portRoles.has(key)) portRoles.set(key, new Set());
            portRoles.get(key).add(role);
        };
        /** @type {Map<string, {rentalIds:Set<number>}>} */
        const portOps = new Map();
        const addOp = (id, rentalId) => {
            if (id == null) return;
            const key = String(id);
            if (!portOps.has(key)) portOps.set(key, { rentalIds: new Set() });
            portOps.get(key).rentalIds.add(Number(rentalId));
        };
        // For transfer ports: track next-port names so we can show "Next: Port X" in the popup.
        /** @type {Map<string, {nextPorts: Set<string>, containers: Set<string>}>} */
        const transferPortCtx = new Map();

        positions.forEach((pos) => {
            const legs = Array.isArray(pos?.route_legs) ? pos.route_legs : [];
            const cur = Number.isInteger(pos?.current_leg_index) ? Number(pos.current_leg_index) : -1;
            if (legs.length) {
                legs.forEach((leg, i) => {
                    if (i === 0) addRole(leg.origin_port_id, 'origin');
                    if (i < legs.length - 1) {
                        addRole(leg.destination_port_id, 'transfer');
                        if (cur >= 1 && i < cur) {
                            addRole(leg.destination_port_id, 'transfer_completed');
                        }
                    }
                    if (i === legs.length - 1) addRole(leg.destination_port_id, 'destination');
                    addOp(leg.origin_port_id, pos.rental_id);
                    addOp(leg.destination_port_id, pos.rental_id);

                    // Populate transfer-port context for enriched popups.
                    if (i < legs.length - 1 && leg.destination_port_id != null) {
                        const key = String(leg.destination_port_id);
                        if (!transferPortCtx.has(key)) transferPortCtx.set(key, { nextPorts: new Set(), containers: new Set() });
                        const ctx = transferPortCtx.get(key);
                        const nextLeg = legs[i + 1];
                        if (nextLeg?.destination_name) ctx.nextPorts.add(nextLeg.destination_name);
                        if (pos.container_serial) ctx.containers.add(pos.container_serial);
                    }
                });
            }
        });

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

        // Per-rental overlay segments: completed (green) / current (blue) / upcoming (amber dashed).
        rentalRouteSegments.forEach((seg) => {
            const path = seg?.path;
            if (!Array.isArray(path) || path.length < 2) {
                return;
            }
            const latLngs = path.map(([lat, lng]) => [lat, lng]);
            const state = String(seg.state || 'upcoming').toLowerCase();
            const style = state === 'completed'
                ? { color: '#10b981', weight: 3, opacity: 0.7 }
                : state === 'current'
                    ? { color: '#2563eb', weight: 4, opacity: 0.9 }
                    : { color: '#f59e0b', weight: 3, opacity: 0.65, dashArray: '2 10' };
            const line = L.polyline(latLngs, style).addTo(map);
            mapLayers.push(line);
        });

        // Port pins — role-specific icons override the neutral port icon.
        ports.forEach((p) => {
            const roles = portRoles.get(String(p.id)) || new Set();
            const roleList = Array.from(roles.values());
            const hasMany = roleList.length > 1;
            const kind = hasMany
                ? 'multi'
                : (roleList.includes('transfer_completed') ? 'transfer_completed' : (roleList[0] || 'port'));
            const icon = buildLeafletIcon(L, kind);
            const m = L.marker([p.latitude, p.longitude], { icon }).addTo(map);

            const label = kind === 'origin'
                ? 'Departure port'
                : kind === 'destination'
                    ? 'Destination port'
                    : kind === 'transfer'
                        ? 'Transfer / transshipment port'
                        : kind === 'transfer_completed'
                            ? 'Transfer port (completed)'
                        : kind === 'multi'
                            ? 'Multiple operations'
                            : 'Port';
            const ops = portOps.get(String(p.id));
            const rentalCount = ops ? ops.rentalIds.size : 0;
            const rolesText = roleList.length ? ` · ${roleList.join(', ')}` : '';
            const rentalText = rentalCount ? ` · ${rentalCount} rental(s)` : '';
            const cityText = p.city ? ` · ${p.city}` : '';

            let extraLines = '';
            if (kind === 'transfer' || kind === 'transfer_completed' || kind === 'multi') {
                const ctx = transferPortCtx.get(String(p.id));
                if (ctx) {
                    if (ctx.nextPorts.size) {
                        extraLines += `<br><span style="font-size:11px;color:#0369a1">Next: ${[...ctx.nextPorts].join(', ')}</span>`;
                    }
                    if (ctx.containers.size) {
                        extraLines += `<br><span style="font-size:11px;color:#64748b">Containers: ${[...ctx.containers].join(', ')}</span>`;
                    }
                }
            }
            if (kind === 'transfer_completed') {
                extraLines += `<br><span style="font-size:11px;color:#64748b">Status: completed</span>`;
            } else if (kind === 'transfer') {
                extraLines += `<br><span style="font-size:11px;color:#64748b">Status: pending / active</span>`;
            }

            m.bindPopup(
                `<strong>${p.name}</strong><br>`
                + `<span style="font-size:11px;color:#64748b">${label}${rolesText}${rentalText}${cityText}</span>`
                + extraLines
            );
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
            if (g.length <= 1) continue;
            g.sort((a, b) => Number(a.vessel_id) - Number(b.vessel_id));
            const n = g.length;
            const r = 0.0008;
            g.forEach((vp, i) => {
                const ang = (2 * Math.PI * i) / n;
                vp._displayLat = vp.latitude + r * Math.sin(ang);
                vp._displayLng = vp.longitude + r * Math.cos(ang);
            });
        }

        const fmt = (s) => String(s || '').replaceAll('_', ' ');
        const vesselPopupLines = (vp) => {
            const lines = [];
            if (vp.origin_name) {
                lines.push(`<span style="font-size:11px;color:#64748b">From: ${vp.origin_name}</span>`);
            }
            if (vp.destination_name) {
                lines.push(`<span style="font-size:11px;color:#64748b">To: ${vp.destination_name}</span>`);
            }
            if (vp.arrival_date) {
                const eta = fmtDate(vp.arrival_date);
                lines.push(`<span style="font-size:11px;color:#0369a1">ETA: ${eta}</span>`);
            }
            const statusLabel = fmt(vp.shipment_status);
            if (statusLabel) {
                lines.push(`<span style="font-size:11px;color:#64748b">Status: ${statusLabel}</span>`);
            }
            const n = Number(vp.rental_cargo_count) || 0;
            if (vp.has_rental_cargo && n > 1) {
                lines.push(`<span style="font-size:11px;color:#0d9488;font-weight:600">${n} rented containers on board</span>`);
            } else if (vp.has_rental_cargo) {
                lines.push(`<span style="font-size:11px;color:#0d9488;font-weight:600">Rented cargo on board</span>`);
            }
            if (vp.is_user_shipment) {
                lines.push(`<span style="font-size:11px;color:#2563eb;font-weight:600">Your cargo on this vessel</span>`);
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
            const isMine = Boolean(vp.is_user_shipment);
            const status = String(vp.shipment_status || '').toLowerCase();
            const heading = Number(vp.heading ?? 0);

            let kind;
            kind = isMine ? 'vessel_user' : 'vessel_fleet';
            const icon = buildLeafletIcon(L, kind, { heading });
            const m = L.marker([plat, plng], { icon }).addTo(map);
            const header = kind === 'unloading'
                ? `Vessel unloading · ${vp.vessel_name ?? 'Vessel'}`
                : kind === 'delivered'
                    ? `Delivery completed · ${vp.vessel_name ?? 'Vessel'}`
                    : `${isMine ? 'Your vessel' : 'Vessel'} — ${vp.vessel_name ?? 'Fleet'}`;
            m.bindPopup(`<strong>${header}</strong><br>${vesselPopupLines(vp)}`);
            mapLayers.push(m);
        });

        positions.forEach((pos) => {
            if (pos.latitude == null || pos.longitude == null) {
                return;
            }
            if (pos.on_vessel) {
                // Vessel marker already represents this rental on the map.
                return;
            }

            const phase = String(pos.shipping_phase || pos.logistics_phase || '').toLowerCase();
            const kind = phase === 'post_arrival'
                ? 'delivered'
                : phase === 'at_destination'
                    ? 'unloading'
                    : 'container';

            const icon = buildLeafletIcon(L, kind);
            const m = L.marker([pos.latitude, pos.longitude], { icon }).addTo(map);

            const header = kind === 'delivered'
                ? 'Container delivered'
                : kind === 'unloading'
                    ? 'Container at destination (unloading)'
                    : phase === 'pre_departure'
                        ? 'Container at origin port'
                        : 'Your container';
            const popupLines = [`<strong>${header}</strong>`, `Rental #${pos.rental_id} · ${pos.container_serial ?? 'Container'}`];
            if (pos.origin?.name && pos.destination?.name) {
                popupLines.push(`<span style="font-size:11px;color:#64748b">${pos.origin.name} → ${pos.destination.name}</span>`);
            }
            if (phase) {
                popupLines.push(`<span style="font-size:11px;color:#0369a1">Phase: ${fmt(phase)}</span>`);
            }
            if (pos.rental_status) {
                popupLines.push(`<span style="font-size:11px;color:#64748b">Rental: ${fmt(pos.rental_status)}</span>`);
            }
            if (pos.payment_status) {
                popupLines.push(`<span style="font-size:11px;color:#64748b">Payment: ${fmt(pos.payment_status)}</span>`);
            }
            const legs = Array.isArray(pos.route_legs) ? pos.route_legs : [];
            const legCount = Number(pos.leg_count) || legs.length;
            if (pos.is_multi_hop && legs.length > 1) {
                const intermediates = legs
                    .slice(0, -1)
                    .map((leg) => leg?.destination_name)
                    .filter(Boolean);
                const via = intermediates.length ? ` via ${intermediates.join(', ')}` : '';
                popupLines.push(`<span style="font-size:11px;color:#64748b">Transshipment route (${legCount} legs)${via}</span>`);
                const cur = Number.isInteger(pos.current_leg_index) ? Number(pos.current_leg_index) : -1;
                legs.forEach((leg, i) => {
                    if (!leg?.origin_name || !leg?.destination_name) return;
                    const active = i === cur ? ' font-weight:600;color:#0f172a;' : '';
                    popupLines.push(
                        `<span style="font-size:11px;color:#64748b;${active}">Leg ${i + 1}: ${leg.origin_name} → ${leg.destination_name}${leg.estimated_days ? ` · ${leg.estimated_days} d` : ''}</span>`
                    );
                });
            } else {
                popupLines.push(`<span style="font-size:11px;color:#64748b">Direct route</span>`);
            }

            m.bindPopup(popupLines.join('<br>'));
            mapLayers.push(m);
        });

        if (mapLayers.length === 1) {
            const only = mapLayers[0];
            const c = typeof only.getLatLng === 'function' ? only.getLatLng() : only.getBounds().getCenter();
            map.setView(c, 6);
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
    <!-- relative + z-0 creates a CSS stacking context that keeps Leaflet's internal z-indexes
         (200–1000) contained below the sticky AppTopbar (z-30). -->
    <div class="logistics-map-wrapper relative z-0 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Logistics</p>
                <h2 class="text-lg font-bold text-slate-900">Ports, routes & fleet</h2>
                <p class="mt-1 text-xs text-slate-600">
                    Origin <span class="font-semibold text-blue-700">blue</span> · destination <span class="font-semibold text-red-600">red</span> · transfer <span class="font-semibold text-amber-600">amber</span>. Vessel arrows rotate to show heading. Green lines = completed legs, blue = current, amber = upcoming.
                </p>
            </div>
        </div>
        <p v-if="loadError" class="mb-2 text-sm text-rose-600">{{ loadError }}</p>
        <div ref="el" class="h-[22rem] w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-inner" />
    </div>
</template>
