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
