<?php

declare(strict_types=1);

/**
 * Periodic housekeeping and scheduled email.
 *
 *   php bin/maintenance.php
 *
 * Run it hourly from cron. Appointment reminders and trial warnings are only
 * ever sent from here, so without the cron job nobody gets them. (Completing
 * overdue bookings and expiring lapsed plans also happen when an admin opens
 * the dashboard, but that is a fallback, not a schedule.)
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\Config;
use App\Core\RememberToken;
use App\Core\Url;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\Subscription;
use App\Services\Emails;

cli_heading('Maintenance — ' . date('Y-m-d H:i'));

try {
    // Appointments that finished more than a day ago and were never closed out.
    // Without this, ratings and revenue sit waiting on an artist to tick a box.
    $completed = Booking::autoCompleteOverdue();
    cli_ok("Auto-completed {$completed} overdue booking(s)");

    // A missed webhook must not leave someone on a paid tier indefinitely.
    $expired = Subscription::expireLapsed();
    cli_ok("Expired {$expired} lapsed subscription(s)");

    // Day-before reminders, to both sides. Claimed one at a time so two
    // overlapping runs cannot send the same reminder twice.
    $reminded = 0;
    foreach (Booking::dueForReminder() as $booking) {
        if (!Booking::markReminded((int) $booking['id'])) {
            continue;
        }

        $path = '/bookings/' . (int) $booking['id'];

        Emails::appointmentReminder(
            ['email' => $booking['customer_email'], 'full_name' => $booking['customer_name']],
            (string) $booking['service_name'],
            (string) $booking['starts_at'],
            (string) ($booking['display_name'] ?? $booking['artist_name']),
            $path
        );
        Emails::appointmentReminder(
            ['email' => $booking['artist_email'], 'full_name' => $booking['artist_name']],
            (string) $booking['service_name'],
            (string) $booking['starts_at'],
            (string) $booking['customer_name'],
            $path
        );
        $reminded++;
    }
    cli_ok("Sent {$reminded} appointment reminder(s)");

    // Warn artists before their trial runs out — once, by notification and email.
    $warnDays = (int) Config::get('app.subscription.trial_warn_days', 3);
    $warned   = 0;
    foreach (Subscription::trialsEndingWithin($warnDays) as $artist) {
        if (!Subscription::markTrialWarned((int) $artist['id'])) {
            continue;
        }

        Notification::push(
            (int) $artist['id'],
            'subscription.trial_ending',
            'Your free trial ends ' . date('F j', (int) strtotime((string) $artist['trial_ends_at'])),
            'After that your profile is hidden from search and you cannot take new bookings until you choose a plan. '
                . 'Subscribe now and your first payment is not taken until the trial ends.',
            Url::to('/artist/subscription')
        );
        $warned++;
    }
    cli_ok("Warned {$warned} artist(s) that their trial is ending");

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
