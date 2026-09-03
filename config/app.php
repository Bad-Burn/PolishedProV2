<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'name'     => Config::env('APP_NAME', 'PolishedPro'),
    'env'      => Config::env('APP_ENV', 'production'),
    'debug'    => Config::env('APP_DEBUG', false) === true,
    'url'      => Config::env('APP_URL', ''),
    'timezone' => Config::env('APP_TIMEZONE', 'Asia/Manila'),
    'key'      => Config::env('APP_KEY', ''),

    'support_email' => Config::env('SUPPORT_EMAIL', 'support@polishedpro.test'),

    'session' => [
        'name'              => 'polishedpro_session',
        'idle_timeout'      => (int) Config::env('SESSION_IDLE_TIMEOUT', 3600),      // 1 hour without activity
        'absolute_timeout'  => (int) Config::env('SESSION_ABSOLUTE_TIMEOUT', 43200), // 12 hours maximum
        'rotate_every'      => 900,                                                  // new session id every 15 min
        'remember_days'     => (int) Config::env('REMEMBER_DAYS', 30),
    ],

    'security' => [
        'min_password_length' => 10,
        'login_max_attempts'  => (int) Config::env('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => (int) Config::env('LOGIN_DECAY_MINUTES', 15),
        'reset_token_ttl'     => 3600, // password reset links last one hour
    ],

    'booking' => [
        // Customers cannot book a slot starting sooner than this.
        'min_lead_time_minutes' => (int) Config::env('BOOKING_MIN_LEAD_MINUTES', 120),
        // How far ahead the calendar opens.
        'max_advance_days'      => (int) Config::env('BOOKING_MAX_ADVANCE_DAYS', 60),
        // Grid granularity for generated slots.
        'slot_interval_minutes' => 15,
        // Customers may cancel free of charge up to this point.
        'free_cancel_hours'     => 24,
    ],

    'pagination' => [
        'per_page' => 12,
    ],

    /**
     * Artist subscriptions.
     *
     * There is no free tier. A new artist gets a trial long enough to build a
     * profile and take their first bookings; after that they subscribe or go
     * quiet. `trial_plan_code` decides what the trial actually grants — point
     * it at the plan you want people to experience, not the cheapest one.
     */
    'subscription' => [
        'trial_days'      => (int) Config::env('TRIAL_DAYS', 14),
        'trial_plan_code' => (string) Config::env('TRIAL_PLAN_CODE', 'studio'),
        // Warn the artist this many days out from the end of their trial.
        'trial_warn_days' => 3,
    ],
];
