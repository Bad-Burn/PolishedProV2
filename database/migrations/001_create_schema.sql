-- ===========================================================================
-- PolishedPro v2 — core schema (MySQL 5.7+ / MariaDB 10.3+)
--
-- Notable changes from v1:
--   * roles renamed to customer / artist / admin
--   * artist-specific columns moved out of `users` into `artist_profiles`
--   * real availability model (weekly rules + dated blocks) instead of free text
--   * bookings gained an end time, a reference code and an event timeline
--   * plans + subscriptions drive service limits and search placement
--   * the plaintext card table is GONE — card data never touches this database
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- Accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role                    ENUM('customer','artist','admin') NOT NULL DEFAULT 'customer',
    full_name               VARCHAR(120)  NOT NULL,
    email                   VARCHAR(190)  NOT NULL,
    phone                   VARCHAR(30)   NULL,
    password_hash           VARCHAR(255)  NOT NULL,
    avatar_path             VARCHAR(255)  NULL,
    status                  ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',

    -- Artists start unapproved; admin sets this to allow them to take bookings.
    approved_at             DATETIME      NULL,
    email_verified_at       DATETIME      NULL,

    -- Optional TOTP two-factor. Secret is only meaningful once confirmed.
    two_factor_secret       VARCHAR(64)   NULL,
    two_factor_confirmed_at DATETIME      NULL,
    two_factor_recovery     TEXT          NULL,

    last_login_at           DATETIME      NULL,
    created_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status),
    KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Artist profiles (1:1 with a user whose role = 'artist')
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS artist_profiles (
    user_id            INT UNSIGNED NOT NULL PRIMARY KEY,
    display_name       VARCHAR(120)  NOT NULL,
    slug               VARCHAR(140)  NOT NULL,
    headline           VARCHAR(160)  NULL,
    bio                TEXT          NULL,
    city               VARCHAR(90)   NULL,
    area               VARCHAR(120)  NULL,
    studio_address     VARCHAR(255)  NULL,
    years_experience   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    instagram          VARCHAR(80)   NULL,
    website            VARCHAR(190)  NULL,
    cover_path         VARCHAR(255)  NULL,

    is_verified        TINYINT(1)    NOT NULL DEFAULT 0,
    instant_book       TINYINT(1)    NOT NULL DEFAULT 0,
    accepts_home_service TINYINT(1)  NOT NULL DEFAULT 0,

    -- Admin-granted manual boost, independent of the paid plan.
    featured_until     DATETIME      NULL,

    -- Denormalised aggregates, refreshed when a review or booking settles.
    rating_avg         DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    rating_count       INT UNSIGNED  NOT NULL DEFAULT 0,
    completed_bookings INT UNSIGNED  NOT NULL DEFAULT 0,

    cancellation_policy VARCHAR(500) NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_artist_slug (slug),
    KEY idx_artist_city (city),
    KEY idx_artist_rating (rating_avg, rating_count),
    KEY idx_artist_featured (featured_until),
    CONSTRAINT fk_artist_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catalogue
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_categories (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(90)  NOT NULL,
    description VARCHAR(255) NULL,
    icon        VARCHAR(40)  NULL,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artist_id      INT UNSIGNED NOT NULL,
    category_id    SMALLINT UNSIGNED NULL,
    name           VARCHAR(140) NOT NULL,
    description    TEXT         NULL,
    duration_min   SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    -- Clean-up / turnaround time reserved after the appointment.
    buffer_min     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path     VARCHAR(255) NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    bookings_count INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order     SMALLINT     NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_services_artist (artist_id, is_active),
    KEY idx_services_category (category_id, is_active),
    KEY idx_services_price (price),
    CONSTRAINT fk_services_artist   FOREIGN KEY (artist_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artist_id  INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    image_path VARCHAR(255) NOT NULL,
    caption    VARCHAR(180) NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_portfolio_artist (artist_id, sort_order),
    CONSTRAINT fk_portfolio_artist  FOREIGN KEY (artist_id)  REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_portfolio_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Availability
--   weekday: 0 = Sunday … 6 = Saturday, matching PHP's `w` format.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS availability_rules (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artist_id  INT UNSIGNED NOT NULL,
    weekday    TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time   TIME NOT NULL,
    is_open    TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uq_rule_slot (artist_id, weekday, start_time),
    KEY idx_rules_artist (artist_id, weekday),
    CONSTRAINT fk_rules_artist FOREIGN KEY (artist_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-off closures: holidays, sick days, or a blocked afternoon.
CREATE TABLE IF NOT EXISTS availability_blocks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artist_id   INT UNSIGNED NOT NULL,
    block_date  DATE NOT NULL,
    start_time  TIME NULL,   -- NULL start+end means the whole day is closed
    end_time    TIME NULL,
    reason      VARCHAR(160) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_blocks_artist_date (artist_id, block_date),
    CONSTRAINT fk_blocks_artist FOREIGN KEY (artist_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Bookings
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference           VARCHAR(16) NOT NULL,
    customer_id         INT UNSIGNED NOT NULL,
    artist_id           INT UNSIGNED NOT NULL,
    service_id          INT UNSIGNED NULL,

    -- The service name/price are copied in so historical bookings stay accurate
    -- even after the artist edits or deletes the service.
    service_name        VARCHAR(140) NOT NULL,
    starts_at           DATETIME NOT NULL,
    -- Includes the service buffer, so overlap checks are a simple range test.
    ends_at             DATETIME NOT NULL,
    duration_min        SMALLINT UNSIGNED NOT NULL,

    price               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    platform_fee        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method      ENUM('cash','paypal') NOT NULL DEFAULT 'cash',

    status              ENUM('pending','confirmed','completed','cancelled','declined','no_show')
                        NOT NULL DEFAULT 'pending',

    notes               VARCHAR(500) NULL,
    cancellation_reason VARCHAR(300) NULL,
    cancelled_by        INT UNSIGNED NULL,

    confirmed_at        DATETIME NULL,
    completed_at        DATETIME NULL,
    cancelled_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_booking_reference (reference),
    KEY idx_bookings_customer (customer_id, status),
    -- Drives both the artist's calendar and the double-booking check.
    KEY idx_bookings_artist_window (artist_id, starts_at, ends_at),
    KEY idx_bookings_status_start (status, starts_at),
    CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_bookings_artist   FOREIGN KEY (artist_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_bookings_service  FOREIGN KEY (service_id)  REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only status history shown as a timeline on the booking page.
CREATE TABLE IF NOT EXISTS booking_events (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    actor_id   INT UNSIGNED NULL,
    event      VARCHAR(40)  NOT NULL,
    note       VARCHAR(300) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_events_booking (booking_id, created_at),
    CONSTRAINT fk_events_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_actor   FOREIGN KEY (actor_id)   REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Reviews
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED NOT NULL,
    customer_id  INT UNSIGNED NOT NULL,
    artist_id    INT UNSIGNED NOT NULL,
    service_id   INT UNSIGNED NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    comment      TEXT NULL,
    artist_reply VARCHAR(800) NULL,
    replied_at   DATETIME NULL,
    is_visible   TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One review per completed booking; this is what stops review spam.
    UNIQUE KEY uq_review_booking (booking_id),
    KEY idx_reviews_artist (artist_id, is_visible, created_at),
    CONSTRAINT fk_reviews_booking  FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_reviews_artist   FOREIGN KEY (artist_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_reviews_service  FOREIGN KEY (service_id)  REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
    customer_id INT UNSIGNED NOT NULL,
    artist_id   INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (customer_id, artist_id),
    KEY idx_favorites_artist (artist_id),
    CONSTRAINT fk_fav_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_artist   FOREIGN KEY (artist_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Subscription plans
--   max_services   NULL = unlimited
--   featured_weight ranks artists in search; higher wins ties on rating.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
    id                     SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                   VARCHAR(30)  NOT NULL,
    name                   VARCHAR(60)  NOT NULL,
    tagline                VARCHAR(160) NULL,
    price_monthly          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_yearly           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency               CHAR(3)      NOT NULL DEFAULT 'PHP',

    max_services           SMALLINT UNSIGNED NULL,
    max_portfolio_items    SMALLINT UNSIGNED NULL,
    featured_weight        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    badge                  VARCHAR(30)  NULL,
    has_analytics          TINYINT(1)   NOT NULL DEFAULT 0,
    has_instant_book       TINYINT(1)   NOT NULL DEFAULT 0,

    highlights             TEXT         NULL,   -- JSON array of bullet strings
    paypal_plan_id_monthly VARCHAR(60)  NULL,
    paypal_plan_id_yearly  VARCHAR(60)  NULL,

    is_active              TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order             SMALLINT     NOT NULL DEFAULT 0,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_plan_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                INT UNSIGNED NOT NULL,
    plan_id                SMALLINT UNSIGNED NOT NULL,
    status                 ENUM('pending','active','past_due','cancelled','expired')
                           NOT NULL DEFAULT 'pending',
    billing_cycle          ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',

    paypal_subscription_id VARCHAR(60) NULL,
    -- 'paypal' for live billing, 'manual' when an admin grants a plan.
    provider               ENUM('paypal','manual') NOT NULL DEFAULT 'paypal',

    current_period_start   DATETIME NULL,
    current_period_end     DATETIME NULL,
    cancel_at_period_end   TINYINT(1) NOT NULL DEFAULT 0,
    started_at             DATETIME NULL,
    cancelled_at           DATETIME NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_sub_paypal (paypal_subscription_id),
    KEY idx_sub_user_status (user_id, status),
    KEY idx_sub_period_end (current_period_end),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw webhook payloads, kept for reconciliation and replay protection.
CREATE TABLE IF NOT EXISTS subscription_events (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NULL,
    provider_event_id VARCHAR(64) NULL,
    event_type      VARCHAR(80) NOT NULL,
    payload         LONGTEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_provider_event (provider_event_id),
    KEY idx_subevents_sub (subscription_id, created_at),
    CONSTRAINT fk_subevents_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money that actually moved. No card data — PayPal holds the instrument.
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    purpose         ENUM('subscription','booking') NOT NULL DEFAULT 'subscription',
    subscription_id INT UNSIGNED NULL,
    booking_id      INT UNSIGNED NULL,
    provider        ENUM('paypal','cash','manual') NOT NULL DEFAULT 'paypal',
    provider_txn_id VARCHAR(64) NULL,
    amount          DECIMAL(10,2) NOT NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'PHP',
    status          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_payment_txn (provider_txn_id),
    KEY idx_payments_user (user_id, created_at),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notifications
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL,
    title      VARCHAR(140) NOT NULL,
    body       VARCHAR(400) NULL,
    link       VARCHAR(255) NULL,
    read_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notifications_user (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Security infrastructure
-- ---------------------------------------------------------------------------

-- Reset links are stored hashed: a database leak yields no usable links.
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Stay signed in" tokens: public selector + hashed single-use validator.
CREATE TABLE IF NOT EXISTS remember_tokens (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    selector       CHAR(18) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expires_at     DATETIME NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Throttling counters. `limit_key` is an HMAC, so no raw emails or IPs land here.
CREATE TABLE IF NOT EXISTS rate_limits (
    limit_key  CHAR(64) NOT NULL PRIMARY KEY,
    attempts   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    KEY idx_rate_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT UNSIGNED NULL,
    action      VARCHAR(80)  NOT NULL,
    target_type VARCHAR(40)  NULL,
    target_id   INT UNSIGNED NULL,
    meta        TEXT         NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_audit_actor (actor_id, created_at),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable platform settings so the admin does not need to touch code.
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
