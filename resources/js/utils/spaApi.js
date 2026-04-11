import axios from 'axios';

/**
 * Same-origin paths for rental JSON APIs. Ziggy's route() often uses APP_URL; if the user opens the
 * app via another host (127.0.0.1 vs localhost, Docker port, etc.), absolute URLs drop session cookies
 * and auth:sanctum returns 401 — polling then never updates the UI.
 */
export function rentalJsonApiPath(rentalId, suffix) {
    const id = encodeURIComponent(String(rentalId));

    return `/api/rentals/${id}${suffix}`;
}

let sanctumCsrfPrimed = false;

/** Call once before first stateful axios request to /api/* (Sanctum SPA cookie + XSRF). */
export async function primeSanctumCsrfOnce() {
    if (sanctumCsrfPrimed || typeof window === 'undefined') {
        return;
    }
    try {
        await axios.get('/sanctum/csrf-cookie');
        sanctumCsrfPrimed = true;
    } catch {
        /* next poll will retry */
    }
}
