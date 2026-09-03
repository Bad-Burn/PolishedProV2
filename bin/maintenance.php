<?php

declare(strict_types=1);

/**
 * Periodic housekeeping.
 *
 *   php bin/maintenance.php
 *
 * The application also performs these opportunistically when an admin loads the
 * dashboard, so it works without cron. Running it nightly is tidier on a busy
 * site and keeps the admin dashboard fast.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\RememberToken;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\Subscription;

cli_heading('Maintenance — ' . date('Y-m-d H:i'));

try {
    // Appointments that finished more than a day ago and were never closed out.
    // Without this, ratings and revenue sit waiting on an artist to tick a box.
    $completed = Booking::autoCompleteOverdue();
    cli_ok("Auto-completed {$completed} overdue booking(s)");

    // A missed webhook must not leave someone on a paid tier indefinitely.
    $expired = Subscription::expireLapsed();
    cli_ok("Expired {$expired} lapsed subscription(s)");

    PasswordReset::purgeExpired();
    cli_ok('Purged expired password reset tokens');

    RememberToken::purgeExpired();
    cli_ok('Purged expired remember-me tokens');

    $notifications = Notification::purgeOld();
    cli_ok("Removed {$notifications} read notification(s) older than 90 days");
} catch (Throwable $e) {
    cli_error($e->getMessage());
    exit(1);
}

cli_line('');
cli_ok('Done.');
