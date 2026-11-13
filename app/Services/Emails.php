<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Mailer;
use App\Core\Url;
use App\Core\View;

/**
 * Every email the application sends, worded in one place.
 *
 * Most mail is a copy of an in-app notification (see Notification::push), so
 * people hear about a booking without having to be signed in. The rest are
 * messages that only make sense by email: password reset, address
 * verification, and appointment reminders.
 */
final class Emails
{
    /**
     * Mirror an in-app notification by email.
     *
     * @param array{email:string, full_name:string} $user
     */
    public static function notification(array $user, string $title, ?string $body, ?string $link): void
    {
        self::send(
            $user,
            $title,
            $title,
            $body === null || $body === '' ? [] : [$body],
            $link === null ? null : Url::absoluteLink($link),
            'View details'
        );
    }

    /** @param array{email:string, full_name:string} $user */
    public static function passwordReset(array $user, string $resetUrl): void
    {
        $minutes = (int) ceil((int) Config::get('app.security.reset_token_ttl', 3600) / 60);

        self::send(
            $user,
            'Reset your password',
            'Reset your password',
            [
                'Someone — hopefully you — asked to reset the password for your account. The link below works once, for the next ' . $minutes . ' minutes.',
                'If you did not ask for this, ignore this email. Your password stays as it is.',
            ],
            $resetUrl,
            'Choose a new password'
        );
    }

    /** @param array{email:string, full_name:string} $user */
    public static function verifyEmail(array $user, string $verifyUrl): void
    {
        self::send(
            $user,
            'Confirm your email address',
            'Confirm your email address',
            [
                'Confirm this is your address so artists and clients can reach you about appointments. You need to do this once before you can book.',
                'If you did not create an account, ignore this email.',
            ],
            $verifyUrl,
            'Confirm my email'
        );
    }

    /**
     * The day-before reminder. Sent to both sides of the appointment, since a
     * forgotten booking costs the artist as much as the client.
     *
     * @param array{email:string, full_name:string} $user
     */
    public static function appointmentReminder(array $user, string $service, string $startsAt, string $withName, string $bookingPath): void
    {
        $starts = (int) strtotime($startsAt);
        $when   = date('l, F j \a\t g:i A', $starts);
        // The window is "the next 24 hours", which is often still today.
        $day    = date('Y-m-d', $starts) === date('Y-m-d') ? 'today' : 'tomorrow';

        self::send(
            $user,
            'Reminder: ' . $service . ' ' . $day . ' at ' . date('g:i A', $starts),
            'See you ' . $day,
            [
                $service . ' with ' . $withName . ' — ' . $when . '.',
                'Need to change plans? Cancel from the booking page as early as you can so the slot can go to someone else.',
            ],
            Url::absolute($bookingPath),
            'View booking'
        );
    }

    /**
     * @param array{email:string, full_name:string} $user
     * @param array<int, string> $paragraphs
     */
    private static function send(array $user, string $subject, string $heading, array $paragraphs, ?string $actionUrl, string $actionLabel): void
    {
        $appName   = (string) Config::get('app.name', 'PolishedPro');
        $firstName = trim(explode(' ', trim((string) $user['full_name']))[0] ?? '');
        $greeting  = $firstName === '' ? 'Hello,' : 'Hi ' . $firstName . ',';
        $footer    = 'You are receiving this because you have a ' . $appName . ' account. Questions? Write to '
                   . Config::get('app.support_email') . '.';

        $html = View::render('emails/message', [
            'appName'     => $appName,
            'greeting'    => $greeting,
            'heading'     => $heading,
            'paragraphs'  => $paragraphs,
            'actionUrl'   => $actionUrl,
            'actionLabel' => $actionLabel,
            'footer'      => $footer,
        ]);

        $text = $greeting . "\n\n" . $heading . "\n\n"
              . implode("\n\n", $paragraphs)
              . ($actionUrl === null ? '' : "\n\n" . $actionLabel . ': ' . $actionUrl)
              . "\n\n-- \n" . $footer . "\n";

        Mailer::queue((string) $user['email'], (string) $user['full_name'], $subject . ' · ' . $appName, $html, $text);
    }
}
