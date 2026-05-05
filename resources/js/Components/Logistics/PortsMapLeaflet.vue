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

const escapeHtml = (s) =>
    String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const PORT_ACTIVITY_POPUP_LIMIT = 4;

/** Strip "Port of ", trim length for popups */
const shortPortName = (name) => {
    if (!name) return '';
    let s = String(name).replace(/^Port of /i, '').trim();
    if (s.length > 16) s = `${s.slice(0, 14)}…`;
    return s;
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

            const activities = Array.isArray(p.activities) ? p.activities : [];
            let activityBlock = '';
            if (activities.length) {
                const shown = activities.slice(0, PORT_ACTIVITY_POPUP_LIMIT);
                const more = activities.length - shown.length;
                const parts = [
                    '<div style="margin-top:4px;border-top:1px solid #e2e8f0;padding-top:4px;max-width:240px">',
                ];
                shown.forEach((a) => {
                    const sub = a.sub ? ` <span style="opacity:.65">${escapeHtml(a.sub)}</span>` : '';
                    parts.push(
                        `<div style="font-size:10px;line-height:1.3;color:#0f172a;word-break:break-word;margin-top:3px">${escapeHtml(a.summary)}${sub}</div>`,
                    );
                });
                if (more > 0) {
                    parts.push(`<div style="margin-top:3px;font-size:9px;color:#94a3b8">+${more}</div>`);
                }
                parts.push('</div>');
                activityBlock = parts.join('');
            }

            let extraLines = '';
            const showTransferExtras = activities.length === 0;
            if (showTransferExtras && (kind === 'transfer' || kind === 'transfer_completed' || kind === 'multi')) {
                const ctx = transferPortCtx.get(String(p.id));
                if (ctx) {
                    if (ctx.nextPorts.size) {
                        const nextShort = [...ctx.nextPorts].map((n) => shortPortName(n)).map(escapeHtml).join(', ');
                        extraLines += `<br><span style="font-size:9px;color:#0369a1">→ ${nextShort}</span>`;
                    }
                    if (ctx.containers.size) {
                        extraLines += `<br><span style="font-size:9px;color:#64748b">${[...ctx.containers].map(escapeHtml).join(', ')}</span>`;
                    }
                }
            }
            if (showTransferExtras && kind === 'transfer_completed') {
                extraLines += `<br><span style="font-size:9px;color:#94a3b8">done</span>`;
            } else if (showTransferExtras && kind === 'transfer') {
                extraLines += `<br><span style="font-size:9px;color:#94a3b8">hub</span>`;
            }

            const title = escapeHtml(shortPortName(p.name) || p.name);
            const subHead = activities.length
                ? `<span style="font-size:9px;color:#64748b">${rentalCount ? `${rentalCount}·` : ''}${escapeHtml(p.city || '')}</span>`
                : `<span style="font-size:9px;color:#64748b">${escapeHtml(label)}${rentalCount ? ` ·${rentalCount}` : ''}${p.city ? ` ·${escapeHtml(p.city)}` : ''}</span>`;

            m.bindPopup(
                `<div class="logistics-map-popup-inner"><strong style="font-size:12px">${title}</strong> ${subHead}<br>`
                + activityBlock
                + extraLines
                + '</div>',
                { maxWidth: 260, className: 'logistics-map-popup-wrap' },
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
            const o = shortPortName(vp.origin_name);
            const d = shortPortName(vp.destination_name);
            const bits = [];
            if (o && d) {
                bits.push(`<span style="font-size:9px;color:#475569">${escapeHtml(o)}→${escapeHtml(d)}</span>`);
            }
            if (vp.arrival_date) {
                const eta = fmtDate(vp.arrival_date);
                bits.push(`<span style="font-size:9px;color:#0369a1">↓${eta}</span>`);
            }
            const n = Number(vp.rental_cargo_count) || 0;
            if (vp.has_rental_cargo && n > 1) {
                bits.push(`<span style="font-size:9px;color:#0d9488">${n} ctr</span>`);
            } else if (vp.has_rental_cargo) {
                bits.push('<span style="font-size:9px;color:#0d9488">cargo</span>');
            }
            if (vp.is_user_shipment) {
                bits.push('<span style="font-size:9px;color:#2563eb;font-weight:600">yours</span>');
            }
            return bits.join(' · ');
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
            const vname = String(vp.vessel_name || 'Vessel').length > 22
                ? `${String(vp.vessel_name).slice(0, 20)}…`
                : (vp.vessel_name || 'Vessel');
            const header = `${isMine ? '▶ ' : ''}${escapeHtml(vname)}`;
            m.bindPopup(
                `<div style="max-width:220px"><strong style="font-size:11px">${header}</strong><br>${vesselPopupLines(vp)}</div>`,
                { maxWidth: 230, className: 'logistics-map-popup-wrap' },
            );
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

            const tag = kind === 'delivered' ? 'done' : kind === 'unloading' ? 'unload' : phase === 'pre_departure' ? 'wait' : 'ctr';
            const serial = pos.container_serial ? String(pos.container_serial) : '—';
            const serialS = serial.length > 14 ? `${serial.slice(0, 12)}…` : serial;
            const oN = shortPortName(pos.origin?.name);
            const dN = shortPortName(pos.destination?.name);
            const popupLines = [
                `<strong style="font-size:11px">#${pos.rental_id} ${escapeHtml(serialS)}</strong> <span style="font-size:9px;color:#94a3b8">${tag}</span>`,
            ];
            if (oN && dN) {
                popupLines.push(`<span style="font-size:9px;color:#475569">${escapeHtml(oN)}→${escapeHtml(dN)}</span>`);
            }
            const pay = pos.payment_status ? String(pos.payment_status).slice(0, 6) : '';
            popupLines.push(
                `<span style="font-size:9px;color:#64748b">${fmt(phase)}${pay ? ` ·${escapeHtml(pay)}` : ''}</span>`,
            );
            const legs = Array.isArray(pos.route_legs) ? pos.route_legs : [];
            if (pos.is_multi_hop && legs.length > 1) {
                const cur = Number.isInteger(pos.current_leg_index) ? Number(pos.current_leg_index) : -1;
                const curLeg = cur >= 0 && cur < legs.length ? legs[cur] : legs[0];
                if (curLeg?.origin_name && curLeg?.destination_name) {
                    popupLines.push(
                        `<span style="font-size:9px;color:#64748b">leg${cur >= 0 ? cur + 1 : '?'} ${escapeHtml(shortPortName(curLeg.origin_name))}→${escapeHtml(shortPortName(curLeg.destination_name))}</span>`,
                    );
                }
            }

            m.bindPopup(
                `<div style="max-width:220px;line-height:1.25">${popupLines.join('<br>')}</div>`,
                { maxWidth: 230, className: 'logistics-map-popup-wrap' },
            );
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

<style>
/* Popup pane lives under the map div; class is unique to this map. */
.logistics-map-popup-wrap .leaflet-popup-content {
    margin: 6px 8px;
    line-height: 1.2;
}
</style>
