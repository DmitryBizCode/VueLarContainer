# IoT metrics: DB vs monitor UI

Use these checks when the `metrics` table grows but charts stay empty.

1. **Same container**  
   `SELECT container_id FROM rentals WHERE id = <rental_id>;`  
   Compare with `metrics.container_id` for recent rows.

2. **Rental scope**  
   The app shows rows where `rental_id` is **NULL** or matches **any rental of the same user (`user_id`) on that container**.  
   Rows stamped with another tenant’s `rental_id` are hidden.

3. **Time range**  
   Charts use the selected `from` / `to` (with a small server-side buffer). Confirm `recorded_at` falls inside that window.

4. **IoT flag (`iot_active` vs demo charts)**  
   If `containers.iot_active` is **false** for this rental’s container, sensor charts use a **synthetic time grid** (see `IotMonitorSeriesBuilder::syntheticSeries`, step e.g. 2 h). The badge shows **demo** / “Synthetic (not DB)”. **`samples_in_range` is omitted** — the ~12–15 points you see are **not** a count of `metrics` rows.  
   When **`iot_active` is true**, charts load from `metrics` (with rental scope). The UI then shows **In range (DB): N** = rows matching that sensor in the selected window **before** the per-chart cap, and **On chart: M / max K** = points actually plotted (latest **K** ≤ `IOT_MONITOR_CHART_MAX_POINTS`, default 30 in `config/iot_monitor.php`).

5. **“Thousands of rows in DB but n ≈ 15”**  
   **`n` / “On chart” is not total table size.** It is the length of one chart’s series after filters + cap. Thousands of rows may be other sensors, other containers, or outside the selected `from`/`to`. Compare with **In range (DB)** on the card and, in SQL,  
   `SELECT type, COUNT(*) FROM metrics WHERE container_id = ? AND recorded_at BETWEEN ? AND ? GROUP BY type`.

6. **API (polling)**  
   The monitor page polls `GET /api/rentals/{id}/monitor-charts` every ~6s. That response includes **`iot_latest`** (same as `/telemetry`), so the “last DB snapshot” block updates with the charts.  
   With `APP_DEBUG=true`, the same response may include `_debug` (container, rental, lessee user, panel count).

## Config

- **`IOT_MONITOR_CHART_MAX_POINTS`** — max points sent per sensor chart (default **30**). See `config/iot_monitor.php`.
