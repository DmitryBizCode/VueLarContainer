# VueLarContainer — Database Schema Description

This document accompanies `database_diagram.drawio` (open in [diagrams.net](https://app.diagrams.net) or the Draw.io desktop / IntelliJ plugin) and explains, in narrative form, the structure of the project database. Everything below was extracted directly from the project's Laravel migrations (`database/migrations/`) and Eloquent models (`app/Models/`); no tables were invented.

---

## 1. What the database stores

The database backs a **container-shipping platform**. It keeps track of:

* **People and access** — registered users with roles (`client`, `admin`, `operator`, `ops`), their countries, sessions, password-reset tokens, in-app notifications, internal user-to-user messages and Telegram channel links.
* **Geography and fleet** — countries, ports, sea routes between port pairs, container owners and the vessels that actually move cargo.
* **Containers** — the physical shipping containers themselves, their dimensions, current status, current port, owning company, IoT sensor configuration and maintenance history.
* **Rentals (the request flow)** — a client's container-rental request with full cargo metadata (incoterms, hazardous-goods flags, temperature ranges, customs codes, delivery preferences, pricing, approvals, contract PDF, etc.). Each rental can be broken down into ordered route segments and is described by one or more cargo manifests.
* **Pre-conversion leads** — the `inquiries` table captures contact-form / Telegram-bot leads that may later be converted into a `users` row.
* **Shipments** — physical voyages of a vessel along a route, plus the line-items (containers / rentals) loaded onto each shipment, and any incidents that occurred.
* **Finance** — `transactions` records every payment / refund attached to a rental.
* **Events, history and IoT** — IoT readings (`metrics`), a tamper-evident IoT audit chain (`iot_audit_chain`), per-user dashboard layouts, action history (`activity_logs`) and per-request HTTP/geo telemetry (`request_logs`).
* **Framework plumbing** — Laravel queue (`jobs`, `job_batches`, `failed_jobs`) and cache (`cache`, `cache_locks`) tables that exist for the framework to function but carry no business data.

---

## 2. Central tables

Two tables are the gravitational centre of the schema:

| Table        | Why it is central |
|--------------|-------------------|
| **`users`**  | Every business action is attributed to a user. `rentals.user_id`, `rentals.reviewed_by`, `rentals.payment_approved_by`, `inquiries.submitted_by_user_id` / `converted_user_id`, `notifications.user_id`, `user_messages.recipient_user_id` / `sender_user_id`, `iot_audit_chain.user_id`, `monitor_chart_layouts.user_id`, `activity_logs.user_id`, `request_logs.user_id`, `user_telegram_links.user_id`, `telegram_link_codes.user_id` and `sessions.user_id` (logical) all point back here. |
| **`rentals`** | The rental is the business transaction at the heart of the platform — it joins a user to a container, a route, ports, a price and a status. Around it pivot `cargo_manifests`, `rental_route_segments`, `transactions`, `shipment_items`, `metrics`, `iot_audit_chain`, `monitor_chart_layouts` and `container_simulation_snapshots`. |

`containers`, `ports` and `routes` are secondary hubs: they are referenced by many tables but they do not themselves drive workflow.

---

## 3. How the entities are connected

### 3.1 Users, roles and access

The `users` table carries a `role` string column with the values `client`, `admin`, `operator`, `ops` (confirmed in `app/Http/Middleware/EnsureUserIsAdmin.php` and `app/Http/Controllers/Admin/AdminUserController.php`). There is no separate `roles` table; access is decided in middleware from this column. Each user belongs to one country (`users.country_id → countries.id`).

Authentication / messaging satellites:

* `password_reset_tokens` is keyed by `email` (not `user_id`).
* `sessions.user_id` is an indexed but **un-constrained** logical foreign key to `users.id`; that is normal for Laravel session storage but is flagged "FK\*" on the diagram.
* `user_telegram_links` and `telegram_link_codes` connect a user to one or more Telegram chats, with a hashed one-time linking code.
* `notifications` are in-app banners; `user_messages` are an internal inbox between two users (sender and recipient both reference `users.id`).

### 3.2 Geography and fleet

* A `country` has many `ports`.
* A `port` is referenced by many things: `vessels.current_port_id`, `containers.current_port_id`, `rentals.origin_port_id` / `destination_port_id`, `routes.origin_port_id` / `destination_port_id`, `rental_route_segments.from_port_id` / `to_port_id`.
* A `route` is a directed pair `(origin_port_id, destination_port_id)` with an estimated distance, a JSON `sea_path` and a status.
* A `vessel` has an IMO number, capacity in TEU and an optional current port.

### 3.3 Containers, sensors and maintenance

* Each `container` belongs to one `owner` and is currently located at zero-or-one `port`.
* `container_sensors` is a **pivot table** between `containers` and `sensor_types` (with a unique constraint on the pair). The `Container` model exposes both a direct `containerSensors()` `hasMany` and a `sensorTypes()` `belongsToMany` through the pivot.
* `maintenances` records repairs, inspections and routine work for a container.
* `container_simulation_snapshots` stores the current simulated sensor state for a container (one row per container, linked optionally to the active rental).

### 3.4 Rental / request flow

A `rental` ties together everything needed to ship cargo:

```
users (client) ──┐
                 │
containers ─────┤
                 │             ┌──> cargo_manifests        (line-items)
routes (logical)─┤   rentals  ─┤──> rental_route_segments  (multi-leg trips)
                 │             ├──> transactions           (payments)
ports (origin /  │             ├──> shipment_items         (loaded onto shipments)
destination) ────┤             ├──> metrics                (IoT readings)
                 │             ├──> iot_audit_chain        (tamper-evident events)
users (reviewer, │             ├──> monitor_chart_layouts  (UI dashboards)
 payment approver)             └──> container_simulation_snapshots
```

Workflow status lives in `rentals.status` (`pending_approval` → `approved` → `scheduled` → `in_progress` / `active` → `completed`, plus `rejected`, `cancelled`). Payment status is in `rentals.payment_status` and is mirrored by rows in `transactions`. The model declares two whitelists used for IoT access control: `IOT_ELIGIBLE_STATUSES` and `IOT_MONITOR_ACCESS_STATUSES`.

`inquiries` is the lead-capture entry point. When a lead is converted to a real account, `inquiries.converted_user_id` is set. Either the inquirer themselves or a logged-in operator can submit an inquiry (`submitted_by_user_id`).

### 3.5 Shipments and incidents

A `shipment` is the physical voyage of one `vessel` along one `route`, with departure / arrival timestamps, a `tracking_number` and a status. The line-items on a shipment are stored in `shipment_items`, each pointing to a `container` and (optionally) a `rental`. `incidents` log problems against a container and/or a shipment with severity, resolution status and an optional insurance policy number.

### 3.6 Finance / transactions

`transactions` is the single source of truth for money. Every row belongs to one `rental` (`ON DELETE CASCADE`), with `amount`, `currency`, payment method, an external provider id and a status like `pending` / `paid` / `refunded`. `users.commission_rate`, `bonus_type`, `bonus_value` and `countries.interest_tax` are the inputs used by the pricing engine that fills `rentals.price` and `rentals.price_breakdown`.

### 3.7 Events, history and logs

* **`metrics`** — time-series IoT readings keyed by `(container_id, type, recorded_at)`. On PostgreSQL this table is **LIST-partitioned** on `metrics_partition_key` (= `rental_id`, or `-1` for null), which is why the migration uses raw SQL on `pgsql` and a flat table on SQLite. The composite primary key on PostgreSQL is `(id, metrics_partition_key)`.
* **`iot_audit_chain`** — a tamper-evident, hash-chained ledger (`prev_hash`, `row_hash`, `sequence` unique per container) of significant IoT events.
* **`monitor_chart_layouts`** — saved dashboard configurations per user (and optionally per rental).
* **`activity_logs`** — generic before/after audit log for any model; uses string `model_name` + `model_id` rather than a polymorphic constraint, so it is not formally tied to any one table.
* **`request_logs`** — per-HTTP-request telemetry (path, method, geo / browser fingerprint).
* **`maintenances`**, **`incidents`** — domain-specific event tables described above.

### 3.8 Framework / system tables

`jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `password_reset_tokens` and `sessions` are stock Laravel infrastructure tables. They are included on the diagram for completeness (bottom-right group) but they do not participate in the business model.

---

## 4. Why the structure is relational

The schema is relational by design, not by accident:

1. **Single source of truth per concept.** Each real-world entity (a country, a port, a container, a user, a rental, a transaction, a shipment, an incident…) lives in one table only. Other tables reference it by id rather than copying its data.
2. **Foreign-key constraints are declared at the database level.** Almost every cross-table column is created with Laravel's `foreignId(...)->constrained()` (e.g. `containers.owner_id → owners.id`, `rentals.user_id → users.id`, `transactions.rental_id → rentals.id`, `shipment_items.shipment_id → shipments.id` …). The database itself rejects orphan rows.
3. **Deletion semantics are chosen per relationship**, not blanket-applied: `cascadeOnDelete()` is used where the child has no meaning without the parent (`maintenances`, `cargo_manifests`, `transactions`, `rental_route_segments`, `metrics`, `notifications`, `user_telegram_links`, `iot_audit_chain`, `container_simulation_snapshots`, …); `nullOnDelete()` where the child should survive but lose the link (`users.country_id`, `vessels.current_port_id`, `rentals.origin_port_id`, `rentals.reviewed_by`, …); and `onDelete('restrict')` where deletion would corrupt history (`rentals.user_id`, `rentals.container_id`).
4. **A pivot table** (`container_sensors`) implements the many-to-many between `containers` and `sensor_types`, with a `UNIQUE (container_id, sensor_type_id)` and pivot payload (`enabled`, `config`, `sort_order`).
5. **Composite uniqueness and indexing** enforce business rules at the database layer: `iot_audit_chain` has `UNIQUE(container_id, sequence)` to keep the hash chain monotonic per container; `container_simulation_snapshots.container_id` is unique (one snapshot per container); `vessels.imo_number`, `containers.serial_number`, `shipments.tracking_number`, `users.email`, `countries.iso_code` and `user_telegram_links.telegram_chat_id` are unique. `rentals` carries composite indexes on `(status, created_at)` and `(container_id, start_date, end_date)` so that the dispatcher can find conflicting bookings cheaply.
6. **Models mirror the constraints.** Each Eloquent model declares the matching `BelongsTo` / `HasMany` / `BelongsToMany` relationship, which means foreign keys are enforced not only by PostgreSQL/MySQL but also by application-level business code.

---

## 5. How primary and foreign keys support data integrity

* **Primary keys** — every business table uses an auto-incrementing `bigint id`, except `password_reset_tokens` (PK = `email`), `sessions` / `cache` / `cache_locks` / `job_batches` (PK = string `id` or `key`), and PostgreSQL's `metrics`, which has a composite PK `(id, metrics_partition_key)` because of LIST-partitioning. PKs guarantee row uniqueness and serve as the join target for every FK in the system.
* **Foreign keys** are declared on the same column they reference (e.g. `containers.owner_id → owners.id`). This means:
  * The DB will refuse to insert a `rentals` row whose `container_id` does not exist in `containers`.
  * When a parent row is deleted, the DB takes the action chosen for that relationship (cascade / set null / restrict), so children are never orphaned.
  * Joins are unambiguous and indexable, because the DB knows the relationship.
* **Unique constraints** prevent duplicate identities (one user per email, one container per serial, one IMO per vessel, one tracking number per shipment, …).
* **Soft deletes** (`deleted_at` on `users`, `owners`, `ports`, `vessels`, `routes`, `containers`) allow logical deletion without breaking referential integrity for historical rows.
* **Logical foreign keys.** Two columns look like FKs but do not have a database constraint and are therefore marked `FK*` on the diagram and should be verified during code review:
  * `sessions.user_id` → `users.id` — un-constrained on purpose so that the session table can stay independent of the auth model (Laravel's default).
  * `rentals.route_id` → `routes.id` — declared as `unsignedBigInteger(...)->index()` in the migration but **not** wrapped with `constrained()`. The Eloquent model still defines `route(): BelongsTo`, so this is treated as an FK at application level only. It is recommended to add `->references('id')->on('routes')` in a follow-up migration.
* The **`activity_logs.model_id` + `model_name`** pair is a manual polymorphic reference (not a Laravel `morphs()` column) and therefore intentionally has no FK; integrity here is enforced only by the writing code.

---

## 6. Diagram conventions

* Tables are drawn as Draw.io entity-relationship "table" shapes (`shape=table` / `shape=tableRow`), one row per column.
* Each row is prefixed with a key badge inline in the row label: **PK** (primary key), **FK** (foreign-key constraint, declared in the migration), **UK** (unique constraint).
* Logical-only foreign keys (declared as `BelongsTo` in Eloquent but not constrained at the DB level) are labelled `(logical)` next to the column name — currently `sessions.user_id` and `rentals.route_id`.
* Connectors use Draw.io's ER cardinality markers (`ERone` / `ERmany`). The "many" end shows the crow's-foot symbol; the "one" end shows a single bar. Each edge carries a short verb describing the relationship (e.g. `client`, `for`, `during`, `via`, `owned by`).
* Tables are visually grouped into eight bands, each with its own header label and a color:
  * Light blue — Users / Access / Telegram
  * Green — Geography & Routes (countries, ports, vessels, routes)
  * Purple — Owners / Containers / Sensors
  * Yellow — Rentals (central)
  * Orange — Shipments / Operations
  * Red — Finance (transactions)
  * Steel-blue — Communications & UI
  * Tan / beige — Audit / Logs / Telemetry
  * Light grey — Infrastructure (Laravel queues / cache)

---

## 7. Exporting to PNG / SVG

The Draw.io / diagrams.net editor can export the file directly:

1. Open `database_diagram.drawio` in <https://app.diagrams.net> (or the Draw.io desktop app, or the JetBrains "Diagrams.net Integration" plugin in PhpStorm).
2. Choose **File → Export As → PNG…** (raster) or **File → Export As → SVG…** (vector).
3. Recommended settings for inclusion in a written report:
   * **PNG**: enable "Selection only" off, "Include a copy of my diagram" off, zoom 200 %, transparent background off.
   * **SVG**: enable "Embed Images", "Include a copy of my diagram" off.
4. From the command line, if you have the official `drawio-desktop` CLI installed, you can render headlessly:

   ```bash
   drawio --export --format png --scale 2 --output database_diagram.png database_diagram.drawio
   drawio --export --format svg                --output database_diagram.svg database_diagram.drawio
   ```

---

## 8. Summary for the project report

The VueLarContainer database is a classical **third-normal-form relational schema** organised around two pivots — `users` (who acts) and `rentals` (the rental contract). It cleanly separates:

* **identity and access** (`users`, `sessions`, `notifications`, `user_messages`, Telegram link tables),
* **physical assets** (`countries`, `ports`, `routes`, `vessels`, `owners`, `containers`, `sensor_types`, `container_sensors`, `maintenances`),
* **business workflow** (`inquiries` → `rentals` → `cargo_manifests`, `rental_route_segments`),
* **operations** (`shipments`, `shipment_items`, `incidents`),
* **money** (`transactions`, plus pricing fields on `rentals`, `users` and `countries`),
* **observability** (`metrics`, `iot_audit_chain`, `container_simulation_snapshots`, `monitor_chart_layouts`, `activity_logs`, `request_logs`).

Foreign-key constraints with explicit deletion semantics, unique constraints on natural identifiers, a proper many-to-many pivot for sensors, and composite indexes on the rental scheduling columns all work together to keep the data consistent without relying on application code alone.
