SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(64) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(64) NOT NULL,
    provider_station_id VARCHAR(64) NOT NULL,
    provider_station_code VARCHAR(32) NOT NULL,
    display_name_en VARCHAR(160) NOT NULL,
    display_name_th VARCHAR(160) NOT NULL,
    river_name_en VARCHAR(120) NOT NULL,
    river_name_th VARCHAR(120) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    gauge_zero_msl DECIMAL(12,6) NULL,
    bank_level_msl DECIMAL(12,6) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    river_role VARCHAR(16) NOT NULL DEFAULT 'upstream',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_station_provider_code (provider, provider_station_code),
    UNIQUE KEY uq_station_provider_id (provider, provider_station_id),
    KEY idx_station_enabled_order (enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS station_thresholds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id BIGINT UNSIGNED NOT NULL,
    threshold_type VARCHAR(32) NOT NULL,
    value_m DECIMAL(10,4) NOT NULL,
    datum VARCHAR(24) NOT NULL DEFAULT 'gauge',
    source_name VARCHAR(255) NOT NULL,
    source_url TEXT NOT NULL,
    verified_at DATE NOT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_station_threshold (station_id, threshold_type),
    CONSTRAINT fk_threshold_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS measurements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id BIGINT UNSIGNED NOT NULL,
    provider_record_id VARCHAR(64) NULL,
    measured_at DATETIME NOT NULL,
    source_measured_at VARCHAR(64) NOT NULL,
    water_level_gauge_m DECIMAL(12,4) NULL,
    water_level_msl_m DECIMAL(12,4) NULL,
    discharge_m3s DECIMAL(16,4) NULL,
    rainfall_mm DECIMAL(12,4) NULL,
    capacity_percent DECIMAL(10,3) NULL,
    source_situation VARCHAR(32) NULL,
    source_status VARCHAR(64) NULL,
    raw_payload_json JSON NULL,
    source_hash CHAR(64) NOT NULL,
    received_at DATETIME NOT NULL,
    revised_at DATETIME NULL,
    revision_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_measurement_station_time (station_id, measured_at),
    KEY idx_measurement_time (measured_at),
    CONSTRAINT fk_measurement_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS measurement_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    measurement_id BIGINT UNSIGNED NOT NULL,
    previous_payload_json JSON NULL,
    replacement_payload_json JSON NULL,
    previous_hash CHAR(64) NOT NULL,
    replacement_hash CHAR(64) NOT NULL,
    revised_at DATETIME NOT NULL,
    CONSTRAINT fk_revision_measurement FOREIGN KEY (measurement_id) REFERENCES measurements(id) ON DELETE CASCADE,
    KEY idx_revision_measurement (measurement_id, revised_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS station_state (
    station_id BIGINT UNSIGNED PRIMARY KEY,
    latest_measurement_id BIGINT UNSIGNED NULL,
    freshness_status VARCHAR(24) NOT NULL DEFAULT 'offline',
    change_1h_m DECIMAL(12,4) NULL,
    change_3h_m DECIMAL(12,4) NULL,
    change_6h_m DECIMAL(12,4) NULL,
    change_12h_m DECIMAL(12,4) NULL,
    change_24h_m DECIMAL(12,4) NULL,
    rate_1h_m_per_hour DECIMAL(12,4) NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_state_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    CONSTRAINT fk_state_measurement FOREIGN KEY (latest_measurement_id) REFERENCES measurements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_health (
    provider VARCHAR(64) PRIMARY KEY,
    provider_type VARCHAR(24) NOT NULL,
    last_success_at DATETIME NULL,
    last_failure_at DATETIME NULL,
    consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    last_error_code VARCHAR(64) NULL,
    last_error_message VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forecast_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    display_name_en VARCHAR(160) NOT NULL,
    display_name_th VARCHAR(160) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    zone_type VARCHAR(24) NOT NULL,
    affects_risk TINYINT(1) NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forecast_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(64) NOT NULL,
    issued_at DATETIME NULL,
    received_at DATETIME NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    raw_payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_forecast_provider_hash (provider, payload_hash),
    KEY idx_forecast_received (provider, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weather_forecast_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    forecast_run_id BIGINT UNSIGNED NOT NULL,
    forecast_zone_id BIGINT UNSIGNED NOT NULL,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NOT NULL,
    rainfall_mm DECIMAL(12,4) NULL,
    rainfall_probability_pct DECIMAL(6,2) NULL,
    weather_code VARCHAR(24) NULL,
    source_status VARCHAR(32) NOT NULL DEFAULT 'ok',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_forecast_point (forecast_run_id, forecast_zone_id, valid_from),
    KEY idx_forecast_zone_valid (forecast_zone_id, valid_from),
    CONSTRAINT fk_point_run FOREIGN KEY (forecast_run_id) REFERENCES forecast_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_point_zone FOREIGN KEY (forecast_zone_id) REFERENCES forecast_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weather_state (
    forecast_zone_id BIGINT UNSIGNED PRIMARY KEY,
    latest_forecast_run_id BIGINT UNSIGNED NULL,
    latest_forecast_received_at DATETIME NULL,
    rain_1h_mm DECIMAL(12,3) NULL,
    rain_3h_mm DECIMAL(12,3) NULL,
    rain_6h_mm DECIMAL(12,3) NULL,
    rain_12h_mm DECIMAL(12,3) NULL,
    rain_24h_mm DECIMAL(12,3) NULL,
    rain_48h_mm DECIMAL(12,3) NULL,
    max_hourly_rain_mm DECIMAL(12,3) NULL,
    max_probability_pct DECIMAL(6,2) NULL,
    freshness_status VARCHAR(24) NOT NULL DEFAULT 'offline',
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_weather_state_zone FOREIGN KEY (forecast_zone_id) REFERENCES forecast_zones(id) ON DELETE CASCADE,
    CONSTRAINT fk_weather_state_run FOREIGN KEY (latest_forecast_run_id) REFERENCES forecast_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_state (
    risk_type VARCHAR(24) PRIMARY KEY,
    severity VARCHAR(24) NOT NULL,
    reason_codes_json JSON NOT NULL,
    message_key VARCHAR(160) NOT NULL,
    context_json JSON NOT NULL,
    calculated_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    severity VARCHAR(24) NOT NULL,
    status VARCHAR(24) NOT NULL,
    title_key VARCHAR(160) NOT NULL,
    message_key VARCHAR(160) NOT NULL,
    reason_codes_json JSON NOT NULL,
    context_json JSON NOT NULL,
    triggered_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    pending_since DATETIME NULL,
    cleared_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_alert_status_time (status, triggered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    from_severity VARCHAR(24) NULL,
    to_severity VARCHAR(24) NULL,
    reason_codes_json JSON NOT NULL,
    occurred_at DATETIME NOT NULL,
    CONSTRAINT fk_alert_event_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    KEY idx_alert_event_time (alert_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_stations (
    alert_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    measurement_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (alert_id, station_id),
    CONSTRAINT fk_alert_station_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_station_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_station_measurement FOREIGN KEY (measurement_id) REFERENCES measurements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collector_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collector VARCHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    records_inserted INT UNSIGNED NOT NULL DEFAULT 0,
    records_updated INT UNSIGNED NOT NULL DEFAULT 0,
    error_code VARCHAR(64) NULL,
    message VARCHAR(500) NULL,
    KEY idx_collector_time (collector, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint_hash CHAR(64) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
    language CHAR(2) NOT NULL DEFAULT 'en',
    client_class VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_success_at DATETIME NULL,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    disabled_at DATETIME NULL,
    UNIQUE KEY uq_push_subscription_endpoint_hash (endpoint_hash),
    KEY idx_push_subscription_active (disabled_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id BIGINT UNSIGNED NOT NULL,
    alert_event_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    severity VARCHAR(24) NOT NULL,
    payload_json JSON NOT NULL,
    subscription_max_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    available_at DATETIME NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    claim_token CHAR(32) NULL,
    claimed_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    last_error_code VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    superseded_at DATETIME NULL,
    expired_at DATETIME NULL,
    UNIQUE KEY uq_notification_outbox_event (alert_event_id),
    KEY idx_notification_outbox_claim (status, available_at, claimed_at),
    CONSTRAINT fk_outbox_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_outbox_alert_event FOREIGN KEY (alert_event_id) REFERENCES alert_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outbox_id BIGINT UNSIGNED NOT NULL,
    alert_event_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    error_code VARCHAR(64) NULL,
    attempted_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_delivery_event_subscription (alert_event_id, subscription_id),
    KEY idx_push_delivery_work (outbox_id, status, available_at),
    CONSTRAINT fk_delivery_outbox FOREIGN KEY (outbox_id) REFERENCES notification_outbox(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_alert_event FOREIGN KEY (alert_event_id) REFERENCES alert_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_rate_limits (
    route VARCHAR(64) NOT NULL,
    client_hash CHAR(64) NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (route, client_hash),
    KEY idx_api_rate_limit_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('001_initial');
INSERT IGNORE INTO schema_migrations (version) VALUES ('002_v1_1_push_and_health');
