-- =============================================================
-- Logistics SWay — повна схема PostgreSQL
-- Згенеровано з Laravel-міграцій
-- =============================================================

-- ------------------------------------------------------------
-- Розширення
-- ------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- 1. countries
-- ============================================================
CREATE TABLE countries (
    id                BIGSERIAL PRIMARY KEY,
    name              VARCHAR(100)    NOT NULL,
    iso_code          VARCHAR(3)      NOT NULL,
    phone_code        VARCHAR(10),
    interest_tax      NUMERIC(5,2)    NOT NULL DEFAULT 0.00,
    created_at        TIMESTAMP(0),
    updated_at        TIMESTAMP(0),
    CONSTRAINT countries_name_unique    UNIQUE (name),
    CONSTRAINT countries_iso_code_unique UNIQUE (iso_code)
);

-- ============================================================
-- 2. users
-- ============================================================
CREATE TABLE users (
    id                                BIGSERIAL PRIMARY KEY,
    first_name                        VARCHAR(50)  NOT NULL,
    last_name                         VARCHAR(50)  NOT NULL,
    company_name                      VARCHAR(100),
    email                             VARCHAR(255) NOT NULL,
    email_verified_at                 TIMESTAMP(0),
    password                          VARCHAR(255) NOT NULL,
    remember_token                    VARCHAR(100),
    phone_number                      VARCHAR(20),
    address                           VARCHAR(255),
    photo                             VARCHAR(255),
    account_status                    VARCHAR(50)  NOT NULL DEFAULT 'pending_verification',
    role                              VARCHAR(50)  NOT NULL DEFAULT 'client',
    commission_rate                   NUMERIC(8,4),
    bonus_type                        VARCHAR(20),
    bonus_value                       NUMERIC(12,2),
    notification_email_enabled        BOOLEAN      NOT NULL DEFAULT TRUE,
    notification_telegram_enabled     BOOLEAN      NOT NULL DEFAULT TRUE,
    country_id                        BIGINT,
    created_at                        TIMESTAMP(0),
    updated_at                        TIMESTAMP(0),
    deleted_at                        TIMESTAMP(0),
    CONSTRAINT users_email_unique UNIQUE (email),
    CONSTRAINT users_country_id_fk FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL
);

-- ============================================================
-- 3. password_reset_tokens
-- ============================================================
CREATE TABLE password_reset_tokens (
    email       VARCHAR(255) NOT NULL PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP(0)
);

-- ============================================================
-- 4. sessions
-- ============================================================
CREATE TABLE sessions (
    id            VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id       BIGINT,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    payload       TEXT         NOT NULL,
    last_activity INTEGER      NOT NULL
);
CREATE INDEX sessions_user_id_idx       ON sessions (user_id);
CREATE INDEX sessions_last_activity_idx ON sessions (last_activity);

-- ============================================================
-- 5. owners
-- ============================================================
CREATE TABLE owners (
    id           BIGSERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(254) NOT NULL,
    phone_number VARCHAR(20)  NOT NULL,
    created_at   TIMESTAMP(0),
    updated_at   TIMESTAMP(0),
    deleted_at   TIMESTAMP(0)
);

-- ============================================================
-- 6. ports
-- ============================================================
CREATE TABLE ports (
    id          BIGSERIAL PRIMARY KEY,
    country_id  BIGINT       NOT NULL,
    name        VARCHAR(100) NOT NULL,
    city        VARCHAR(100) NOT NULL,
    latitude    NUMERIC(10,7),
    longitude   NUMERIC(10,7),
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0),
    deleted_at  TIMESTAMP(0),
    CONSTRAINT ports_country_id_fk FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
);

-- ============================================================
-- 7. vessels
-- ============================================================
CREATE TABLE vessels (
    id                   BIGSERIAL PRIMARY KEY,
    name                 VARCHAR(100) NOT NULL,
    imo_number           VARCHAR(20)  NOT NULL,
    capacity_teu         INTEGER      NOT NULL,
    status               VARCHAR(50)  NOT NULL DEFAULT 'active',
    current_port_id      BIGINT,
    berth_busy_until     TIMESTAMPTZ,
    out_of_service_until TIMESTAMPTZ,
    last_inspection_date DATE,
    created_at           TIMESTAMP(0),
    updated_at           TIMESTAMP(0),
    deleted_at           TIMESTAMP(0),
    CONSTRAINT vessels_imo_number_unique  UNIQUE (imo_number),
    CONSTRAINT vessels_current_port_id_fk FOREIGN KEY (current_port_id) REFERENCES ports (id) ON DELETE SET NULL
);

-- ============================================================
-- 8. containers
-- ============================================================
CREATE TABLE containers (
    id               BIGSERIAL PRIMARY KEY,
    serial_number    VARCHAR(50)  NOT NULL,
    type             VARCHAR(50)  NOT NULL DEFAULT 'standard',
    width            NUMERIC(8,2) NOT NULL,
    length           NUMERIC(8,2) NOT NULL,
    height           NUMERIC(8,2) NOT NULL,
    max_weight       NUMERIC(10,2) NOT NULL,
    manufacture_date DATE,
    photo            VARCHAR(255),
    iot_active       BOOLEAN      NOT NULL DEFAULT FALSE,
    current_status   VARCHAR(50)  NOT NULL DEFAULT 'available',
    owner_id         BIGINT       NOT NULL,
    current_port_id  BIGINT,
    created_at       TIMESTAMP(0),
    updated_at       TIMESTAMP(0),
    deleted_at       TIMESTAMP(0),
    CONSTRAINT containers_serial_number_unique  UNIQUE (serial_number),
    CONSTRAINT containers_owner_id_fk           FOREIGN KEY (owner_id)        REFERENCES owners (id),
    CONSTRAINT containers_current_port_id_fk    FOREIGN KEY (current_port_id) REFERENCES ports (id)
);

-- ============================================================
-- 9. maintenances
-- ============================================================
CREATE TABLE maintenances (
    id               BIGSERIAL PRIMARY KEY,
    container_id     BIGINT       NOT NULL,
    maintenance_date TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maintenance_type VARCHAR(50)  NOT NULL DEFAULT 'routine',
    description      TEXT,
    cost             NUMERIC(15,2) NOT NULL DEFAULT 0.00,
    technician_name  VARCHAR(100),
    created_at       TIMESTAMP(0),
    updated_at       TIMESTAMP(0),
    CONSTRAINT maintenances_container_id_fk FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE
);

-- ============================================================
-- 10. routes
-- ============================================================
CREATE TABLE routes (
    id                    BIGSERIAL PRIMARY KEY,
    origin_port_id        BIGINT   NOT NULL,
    destination_port_id   BIGINT   NOT NULL,
    estimated_days        INTEGER  NOT NULL,
    distance              FLOAT    NOT NULL DEFAULT 0.00,
    sea_path              JSON,
    route_status          VARCHAR(50) NOT NULL DEFAULT 'open',
    created_at            TIMESTAMP(0),
    updated_at            TIMESTAMP(0),
    deleted_at            TIMESTAMP(0),
    CONSTRAINT routes_origin_port_id_fk      FOREIGN KEY (origin_port_id)      REFERENCES ports (id),
    CONSTRAINT routes_destination_port_id_fk FOREIGN KEY (destination_port_id) REFERENCES ports (id)
);

-- ============================================================
-- 11. rentals
-- ============================================================
CREATE TABLE rentals (
    id                          BIGSERIAL    PRIMARY KEY,
    user_id                     BIGINT       NOT NULL,
    container_id                BIGINT       NOT NULL,
    route_id                    BIGINT,
    origin_port_id              BIGINT,
    destination_port_id         BIGINT,
    start_date                  TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date                    TIMESTAMP(0),
    actual_return_date          TIMESTAMP(0),
    rental_days                 INTEGER      NOT NULL DEFAULT 1,
    cargo_types                 JSON,
    cargo_details               TEXT,
    requested_weight            NUMERIC(10,2),
    cargo_volume_cbm            NUMERIC(10,3),
    package_count               INTEGER,
    cargo_value                 NUMERIC(12,2),
    priority                    VARCHAR(20)  NOT NULL DEFAULT 'normal',
    routing_priority            VARCHAR(20),
    incoterm                    VARCHAR(10),
    loading_type                VARCHAR(20)  NOT NULL DEFAULT 'fcl',
    delivery_mode               VARCHAR(30)  NOT NULL DEFAULT 'port_to_port',
    sustainability_pref         VARCHAR(30)  NOT NULL DEFAULT 'standard',
    insurance_required          BOOLEAN      NOT NULL DEFAULT FALSE,
    requires_customs_clearance  BOOLEAN      NOT NULL DEFAULT FALSE,
    hazardous_material          BOOLEAN      NOT NULL DEFAULT FALSE,
    requires_escort             BOOLEAN      NOT NULL DEFAULT FALSE,
    seal_required               BOOLEAN      NOT NULL DEFAULT FALSE,
    un_number                   VARCHAR(20),
    dangerous_goods_class       VARCHAR(20),
    origin_customs_code         VARCHAR(20),
    destination_customs_code    VARCHAR(20),
    temperature_min             NUMERIC(5,2),
    temperature_max             NUMERIC(5,2),
    contact_name                VARCHAR(120),
    contact_phone               VARCHAR(30),
    pickup_address              VARCHAR(255),
    delivery_address            VARCHAR(255),
    pickup_window_start         TIMESTAMP(0),
    pickup_window_end           TIMESTAMP(0),
    quote_expires_at            TIMESTAMP(0),
    terms_accepted              BOOLEAN      NOT NULL DEFAULT FALSE,
    special_requirements        TEXT,
    estimated_distance          NUMERIC(12,2),
    price                       NUMERIC(15,2) NOT NULL DEFAULT 0.00,
    price_breakdown             JSON,
    status                      VARCHAR(50)  NOT NULL DEFAULT 'pending_approval',
    is_telemetry_active         BOOLEAN      NOT NULL DEFAULT TRUE,
    payment_status              VARCHAR(50)  NOT NULL DEFAULT 'unpaid',
    reviewed_by                 BIGINT,
    reviewed_at                 TIMESTAMP(0),
    payment_approved_at         TIMESTAMP(0),
    payment_approved_by         BIGINT,
    rejection_reason            TEXT,
    cancellation_reason         TEXT,
    contract_pdf                VARCHAR(255),
    description                 TEXT,
    created_at                  TIMESTAMP(0),
    updated_at                  TIMESTAMP(0),
    CONSTRAINT rentals_user_id_fk              FOREIGN KEY (user_id)             REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT rentals_container_id_fk         FOREIGN KEY (container_id)        REFERENCES containers (id) ON DELETE RESTRICT,
    CONSTRAINT rentals_origin_port_id_fk       FOREIGN KEY (origin_port_id)      REFERENCES ports (id) ON DELETE SET NULL,
    CONSTRAINT rentals_destination_port_id_fk  FOREIGN KEY (destination_port_id) REFERENCES ports (id) ON DELETE SET NULL,
    CONSTRAINT rentals_reviewed_by_fk          FOREIGN KEY (reviewed_by)         REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT rentals_payment_approved_by_fk  FOREIGN KEY (payment_approved_by) REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX rentals_status_created_idx         ON rentals (status, created_at);
CREATE INDEX rentals_container_dates_idx        ON rentals (container_id, start_date, end_date);

-- ============================================================
-- 12. metrics  (партиційована таблиця)
-- ============================================================
CREATE SEQUENCE metrics_id_seq;

CREATE TABLE metrics (
    id                      BIGINT      NOT NULL DEFAULT nextval('metrics_id_seq'),
    container_id            BIGINT      NOT NULL,
    rental_id               BIGINT,
    type                    VARCHAR(64) NOT NULL,
    value                   NUMERIC(12,4) NOT NULL DEFAULT 0,
    unit                    VARCHAR(100),
    meta                    JSON,
    recorded_at             TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at              TIMESTAMPTZ,
    updated_at              TIMESTAMPTZ,
    metrics_partition_key   BIGINT      NOT NULL,
    PRIMARY KEY (id, metrics_partition_key),
    CONSTRAINT metrics_container_id_fk FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE,
    CONSTRAINT metrics_rental_id_fk    FOREIGN KEY (rental_id)    REFERENCES rentals (id)    ON DELETE SET NULL
) PARTITION BY LIST (metrics_partition_key);

ALTER SEQUENCE metrics_id_seq OWNED BY metrics.id;

CREATE TABLE metrics_p_null PARTITION OF metrics FOR VALUES IN (-1);

CREATE INDEX metrics_container_recorded_idx ON metrics (container_id, recorded_at);
CREATE INDEX metrics_type_recorded_idx      ON metrics (type, recorded_at);
CREATE INDEX metrics_rental_recorded_idx    ON metrics (rental_id, recorded_at);

-- ============================================================
-- 13. shipments
-- ============================================================
CREATE TABLE shipments (
    id                      BIGSERIAL    PRIMARY KEY,
    vessel_id               BIGINT       NOT NULL,
    route_id                BIGINT       NOT NULL,
    leg_sequence            SMALLINT     NOT NULL DEFAULT 1,
    departure_date          TIMESTAMP(0) NOT NULL,
    arrival_date            TIMESTAMP(0) NOT NULL,
    actual_departure_date   TIMESTAMP(0),
    actual_arrival_date     TIMESTAMP(0),
    port_operations_until   TIMESTAMPTZ,
    tracking_number         VARCHAR(50)  NOT NULL,
    status                  VARCHAR(50)  NOT NULL DEFAULT 'scheduled',
    created_at              TIMESTAMP(0),
    updated_at              TIMESTAMP(0),
    CONSTRAINT shipments_tracking_number_unique UNIQUE (tracking_number),
    CONSTRAINT shipments_vessel_id_fk           FOREIGN KEY (vessel_id) REFERENCES vessels (id),
    CONSTRAINT shipments_route_id_fk            FOREIGN KEY (route_id)  REFERENCES routes (id)
);

-- ============================================================
-- 14. shipment_items
-- ============================================================
CREATE TABLE shipment_items (
    id                  BIGSERIAL    PRIMARY KEY,
    shipment_id         BIGINT       NOT NULL,
    container_id        BIGINT       NOT NULL,
    rental_id           BIGINT,
    loaded_at           TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    condition_on_arrival VARCHAR(50) NOT NULL DEFAULT 'good',
    notes               TEXT,
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0),
    CONSTRAINT shipment_items_shipment_id_fk   FOREIGN KEY (shipment_id)  REFERENCES shipments (id)  ON DELETE CASCADE,
    CONSTRAINT shipment_items_container_id_fk  FOREIGN KEY (container_id) REFERENCES containers (id),
    CONSTRAINT shipment_items_rental_id_fk     FOREIGN KEY (rental_id)    REFERENCES rentals (id)
);

-- ============================================================
-- 15. incidents
-- ============================================================
CREATE TABLE incidents (
    id                      BIGSERIAL    PRIMARY KEY,
    type                    VARCHAR(50)  NOT NULL,
    severity                VARCHAR(20)  NOT NULL,
    description             TEXT         NOT NULL,
    container_id            BIGINT,
    shipment_id             BIGINT,
    insurance_policy_number VARCHAR(100),
    reported_at             TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at             TIMESTAMP(0),
    resolution_status       VARCHAR(50)  NOT NULL DEFAULT 'under_investigation',
    created_at              TIMESTAMP(0),
    updated_at              TIMESTAMP(0),
    CONSTRAINT incidents_container_id_fk FOREIGN KEY (container_id) REFERENCES containers (id),
    CONSTRAINT incidents_shipment_id_fk  FOREIGN KEY (shipment_id)  REFERENCES shipments (id)
);

-- ============================================================
-- 16. cargo_manifests
-- ============================================================
CREATE TABLE cargo_manifests (
    id              BIGSERIAL    PRIMARY KEY,
    rental_id       BIGINT       NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    hs_code         VARCHAR(20),
    weight_kg       NUMERIC(12,2) NOT NULL,
    volume_m3       NUMERIC(10,2),
    is_dangerous    BOOLEAN      NOT NULL DEFAULT FALSE,
    declared_value  NUMERIC(15,2),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0),
    CONSTRAINT cargo_manifests_rental_id_fk FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
);

-- ============================================================
-- 17. transactions
-- ============================================================
CREATE TABLE transactions (
    id                   BIGSERIAL    PRIMARY KEY,
    rental_id            BIGINT       NOT NULL,
    amount               NUMERIC(15,2) NOT NULL DEFAULT 0.00,
    currency             VARCHAR(10)  NOT NULL DEFAULT 'USD',
    status               VARCHAR(50)  NOT NULL DEFAULT 'pending',
    external_provider_id VARCHAR(100),
    refund_reason        TEXT,
    status_note          TEXT,
    transaction_date     TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payment_method       VARCHAR(50)  NOT NULL DEFAULT 'card',
    created_at           TIMESTAMP(0),
    updated_at           TIMESTAMP(0),
    CONSTRAINT transactions_rental_id_fk FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE CASCADE
);

-- ============================================================
-- 18. inquiries
-- ============================================================
CREATE TABLE inquiries (
    id                    BIGSERIAL    PRIMARY KEY,
    name                  VARCHAR(255) NOT NULL,
    email                 VARCHAR(255),
    phone_number          VARCHAR(20),
    telegram_username     VARCHAR(100),
    subject               VARCHAR(255),
    message               TEXT         NOT NULL,
    source                VARCHAR(20)  NOT NULL DEFAULT 'website',
    converted_user_id     BIGINT,
    submitted_by_user_id  BIGINT,
    handling_status       VARCHAR(40)  NOT NULL DEFAULT 'new',
    admin_notes           TEXT,
    created_at            TIMESTAMP(0),
    updated_at            TIMESTAMP(0),
    CONSTRAINT inquiries_converted_user_id_fk     FOREIGN KEY (converted_user_id)    REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT inquiries_submitted_by_user_id_fk  FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL
);

-- ============================================================
-- 19. notifications
-- ============================================================
CREATE TABLE notifications (
    id          BIGSERIAL    PRIMARY KEY,
    user_id     BIGINT       NOT NULL,
    title       VARCHAR(255) NOT NULL,
    message     TEXT         NOT NULL,
    type        VARCHAR(50)  NOT NULL DEFAULT 'info',
    action_url  VARCHAR(500),
    is_read     BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0),
    CONSTRAINT notifications_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- ============================================================
-- 20. activity_logs
-- ============================================================
CREATE TABLE activity_logs (
    id                  BIGSERIAL    PRIMARY KEY,
    user_id             BIGINT,
    ip_address          VARCHAR(45),
    user_agent          TEXT,
    action              VARCHAR(255) NOT NULL,
    model_name          VARCHAR(50)  NOT NULL,
    model_id            BIGINT       NOT NULL,
    old_values          JSON,
    new_values          JSON,
    description         VARCHAR(255),
    request_path        VARCHAR(500),
    country_code        VARCHAR(10),
    timezone            VARCHAR(80),
    gmt_offset_minutes  SMALLINT,
    browser             VARCHAR(80),
    device_type         VARCHAR(40),
    created_at          TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT activity_logs_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

-- ============================================================
-- 21. request_logs
-- ============================================================
CREATE TABLE request_logs (
    id                  BIGSERIAL    PRIMARY KEY,
    user_id             BIGINT,
    session_id          VARCHAR(100),
    path                VARCHAR(500) NOT NULL,
    method              VARCHAR(10)  NOT NULL,
    ip_address          VARCHAR(45),
    user_agent          TEXT,
    country_code        VARCHAR(10),
    region              VARCHAR(100),
    city                VARCHAR(100),
    timezone            VARCHAR(80),
    gmt_offset_minutes  SMALLINT,
    browser             VARCHAR(80),
    browser_version     VARCHAR(20),
    device_type         VARCHAR(40),
    platform            VARCHAR(80),
    accept_language     VARCHAR(255),
    referer             TEXT,
    created_at          TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT request_logs_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX request_logs_session_id_idx       ON request_logs (session_id);
CREATE INDEX request_logs_user_created_idx     ON request_logs (user_id, created_at);
CREATE INDEX request_logs_created_at_idx       ON request_logs (created_at);

-- ============================================================
-- 22. rental_route_segments
-- ============================================================
CREATE TABLE rental_route_segments (
    id                          BIGSERIAL   PRIMARY KEY,
    rental_id                   BIGINT      NOT NULL,
    segment_order               SMALLINT    NOT NULL DEFAULT 1,
    from_port_id                BIGINT      NOT NULL,
    to_port_id                  BIGINT      NOT NULL,
    route_id                    BIGINT,
    vessel_id                   BIGINT,
    planned_departure_at        TIMESTAMPTZ,
    planned_arrival_at          TIMESTAMPTZ,
    travel_duration_hours       SMALLINT    NOT NULL DEFAULT 0,
    waiting_time_before_hours   SMALLINT    NOT NULL DEFAULT 0,
    waiting_time_after_hours    SMALLINT    NOT NULL DEFAULT 0,
    status                      VARCHAR(30) NOT NULL DEFAULT 'planned',
    created_at                  TIMESTAMP(0),
    updated_at                  TIMESTAMP(0),
    CONSTRAINT rrs_rental_id_fk    FOREIGN KEY (rental_id)    REFERENCES rentals (id)  ON DELETE CASCADE,
    CONSTRAINT rrs_from_port_id_fk FOREIGN KEY (from_port_id) REFERENCES ports (id),
    CONSTRAINT rrs_to_port_id_fk   FOREIGN KEY (to_port_id)   REFERENCES ports (id),
    CONSTRAINT rrs_route_id_fk     FOREIGN KEY (route_id)     REFERENCES routes (id)   ON DELETE SET NULL,
    CONSTRAINT rrs_vessel_id_fk    FOREIGN KEY (vessel_id)    REFERENCES vessels (id)  ON DELETE SET NULL
);
CREATE INDEX rental_route_segments_rental_order_idx ON rental_route_segments (rental_id, segment_order);

-- ============================================================
-- 23. user_messages
-- ============================================================
CREATE TABLE user_messages (
    id                  BIGSERIAL    PRIMARY KEY,
    recipient_user_id   BIGINT       NOT NULL,
    sender_user_id      BIGINT,
    subject             VARCHAR(255),
    body                TEXT         NOT NULL,
    read_at             TIMESTAMP(0),
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0),
    CONSTRAINT user_messages_recipient_fk FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT user_messages_sender_fk    FOREIGN KEY (sender_user_id)    REFERENCES users (id) ON DELETE SET NULL
);
CREATE INDEX user_messages_recipient_read_idx ON user_messages (recipient_user_id, read_at);

-- ============================================================
-- 24. sensor_types
-- ============================================================
CREATE TABLE sensor_types (
    id              BIGSERIAL    PRIMARY KEY,
    slug            VARCHAR(64)  NOT NULL,
    name            VARCHAR(120) NOT NULL,
    category        VARCHAR(50)  NOT NULL DEFAULT 'general',
    is_optional     BOOLEAN      NOT NULL DEFAULT TRUE,
    telemetry_keys  JSON,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0),
    CONSTRAINT sensor_types_slug_unique UNIQUE (slug)
);

-- ============================================================
-- 25. container_sensors
-- ============================================================
CREATE TABLE container_sensors (
    id              BIGSERIAL PRIMARY KEY,
    container_id    BIGINT    NOT NULL,
    sensor_type_id  BIGINT    NOT NULL,
    enabled         BOOLEAN   NOT NULL DEFAULT TRUE,
    config          JSON,
    sort_order      SMALLINT  NOT NULL DEFAULT 0,
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0),
    CONSTRAINT container_sensors_container_id_fk   FOREIGN KEY (container_id)   REFERENCES containers (id)   ON DELETE CASCADE,
    CONSTRAINT container_sensors_sensor_type_id_fk FOREIGN KEY (sensor_type_id) REFERENCES sensor_types (id) ON DELETE CASCADE,
    CONSTRAINT container_sensors_unique            UNIQUE (container_id, sensor_type_id)
);

-- ============================================================
-- 26. iot_audit_chain
-- ============================================================
CREATE TABLE iot_audit_chain (
    id            BIGSERIAL    PRIMARY KEY,
    container_id  BIGINT       NOT NULL,
    rental_id     BIGINT,
    user_id       BIGINT,
    event_type    VARCHAR(64)  NOT NULL,
    payload       JSON,
    sequence      BIGINT       NOT NULL,
    prev_hash     VARCHAR(64),
    row_hash      VARCHAR(64)  NOT NULL,
    created_at    TIMESTAMP(0),
    updated_at    TIMESTAMP(0),
    CONSTRAINT iot_audit_chain_container_id_fk FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE,
    CONSTRAINT iot_audit_chain_rental_id_fk    FOREIGN KEY (rental_id)    REFERENCES rentals (id)    ON DELETE SET NULL,
    CONSTRAINT iot_audit_chain_user_id_fk      FOREIGN KEY (user_id)      REFERENCES users (id)      ON DELETE SET NULL,
    CONSTRAINT iot_audit_chain_container_seq_unique UNIQUE (container_id, sequence)
);
CREATE INDEX iot_audit_chain_event_type_idx     ON iot_audit_chain (event_type);
CREATE INDEX iot_audit_chain_row_hash_idx       ON iot_audit_chain (row_hash);
CREATE INDEX iot_audit_chain_container_date_idx ON iot_audit_chain (container_id, created_at);

-- ============================================================
-- 27. container_simulation_snapshots
-- ============================================================
CREATE TABLE container_simulation_snapshots (
    id            BIGSERIAL PRIMARY KEY,
    container_id  BIGINT    NOT NULL,
    rental_id     BIGINT,
    sensor_state  JSON      NOT NULL,
    actuators     JSON,
    last_tick_at  TIMESTAMPTZ,
    created_at    TIMESTAMP(0),
    updated_at    TIMESTAMP(0),
    CONSTRAINT css_container_id_fk FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE,
    CONSTRAINT css_rental_id_fk    FOREIGN KEY (rental_id)    REFERENCES rentals (id)    ON DELETE SET NULL,
    CONSTRAINT css_container_id_unique UNIQUE (container_id)
);

-- ============================================================
-- 28. monitor_chart_layouts
-- ============================================================
CREATE TABLE monitor_chart_layouts (
    id          BIGSERIAL    PRIMARY KEY,
    user_id     BIGINT       NOT NULL,
    rental_id   BIGINT,
    name        VARCHAR(120) NOT NULL,
    is_default  BOOLEAN      NOT NULL DEFAULT FALSE,
    config      JSON         NOT NULL,
    created_at  TIMESTAMP(0),
    updated_at  TIMESTAMP(0),
    CONSTRAINT monitor_chart_layouts_user_id_fk   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT monitor_chart_layouts_rental_id_fk FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE SET NULL
);
CREATE INDEX monitor_chart_layouts_user_rental_idx ON monitor_chart_layouts (user_id, rental_id);

-- ============================================================
-- 29. user_telegram_links
-- ============================================================
CREATE TABLE user_telegram_links (
    id                  BIGSERIAL    PRIMARY KEY,
    user_id             BIGINT       NOT NULL,
    telegram_chat_id    BIGINT       NOT NULL,
    telegram_user_id    BIGINT,
    telegram_username   VARCHAR(255),
    first_name          VARCHAR(255),
    last_name           VARCHAR(255),
    status              VARCHAR(32)  NOT NULL DEFAULT 'active',
    linked_at           TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at    TIMESTAMP(0),
    last_error_at       TIMESTAMP(0),
    last_error          VARCHAR(512),
    created_at          TIMESTAMP(0),
    updated_at          TIMESTAMP(0),
    CONSTRAINT user_telegram_links_user_id_fk         FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT user_telegram_links_chat_id_unique      UNIQUE (telegram_chat_id)
);
CREATE INDEX user_telegram_links_user_status_idx ON user_telegram_links (user_id, status);

-- ============================================================
-- 30. telegram_link_codes
-- ============================================================
CREATE TABLE telegram_link_codes (
    id           BIGSERIAL    PRIMARY KEY,
    user_id      BIGINT       NOT NULL,
    code_hash    VARCHAR(64)  NOT NULL,
    expires_at   TIMESTAMP(0) NOT NULL,
    consumed_at  TIMESTAMP(0),
    created_at   TIMESTAMP(0),
    updated_at   TIMESTAMP(0),
    CONSTRAINT telegram_link_codes_user_id_fk    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT telegram_link_codes_hash_unique   UNIQUE (code_hash)
);
CREATE INDEX telegram_link_codes_user_consumed_idx ON telegram_link_codes (user_id, consumed_at);

-- ============================================================
-- 31. cache
-- ============================================================
CREATE TABLE cache (
    key         VARCHAR(255) NOT NULL PRIMARY KEY,
    value       TEXT         NOT NULL,
    expiration  INTEGER      NOT NULL
);

-- ============================================================
-- 32. cache_locks
-- ============================================================
CREATE TABLE cache_locks (
    key         VARCHAR(255) NOT NULL PRIMARY KEY,
    owner       VARCHAR(255) NOT NULL,
    expiration  INTEGER      NOT NULL
);

-- ============================================================
-- 33. jobs
-- ============================================================
CREATE TABLE jobs (
    id           BIGSERIAL PRIMARY KEY,
    queue        VARCHAR(255)   NOT NULL,
    payload      TEXT           NOT NULL,
    attempts     SMALLINT       NOT NULL,
    reserved_at  INTEGER,
    available_at INTEGER        NOT NULL,
    created_at   INTEGER        NOT NULL
);
CREATE INDEX jobs_queue_idx ON jobs (queue);

-- ============================================================
-- 34. job_batches
-- ============================================================
CREATE TABLE job_batches (
    id              VARCHAR(255) NOT NULL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    total_jobs      INTEGER      NOT NULL,
    pending_jobs    INTEGER      NOT NULL,
    failed_jobs     INTEGER      NOT NULL,
    failed_job_ids  TEXT         NOT NULL,
    options         TEXT,
    cancelled_at    INTEGER,
    created_at      INTEGER      NOT NULL,
    finished_at     INTEGER
);

-- ============================================================
-- 35. failed_jobs
-- ============================================================
CREATE TABLE failed_jobs (
    id          BIGSERIAL    PRIMARY KEY,
    uuid        VARCHAR(255) NOT NULL,
    connection  TEXT         NOT NULL,
    queue       TEXT         NOT NULL,
    payload     TEXT         NOT NULL,
    exception   TEXT         NOT NULL,
    failed_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
);
