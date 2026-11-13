<?php

declare(strict_types=1);

use App\Core\Config;

/**
 * Outgoing email.
 *
 * `log` writes every message to storage/logs/mail.log instead of sending it —
 * the right choice on a laptop, and the wrong one anywhere real users sign up.
 * `smtp` works with any provider that offers SMTP: Brevo, Postmark, Amazon
 * SES, Mailgun, Google Workspace.
 */
return [
    'driver' => Config::env('MAIL_DRIVER', 'log'), // log | smtp

    'smtp' => [
        'host'       => Config::env('MAIL_HOST', ''),
        'port'       => (int) Config::env('MAIL_PORT', 587),
        // tls = STARTTLS on 587, ssl = implicit TLS on 465, none = plain (local testing only)
        'encryption' => Config::env('MAIL_ENCRYPTION', 'tls'),
        'username'   => Config::env('MAIL_USERNAME', ''),
        'password'   => Config::env('MAIL_PASSWORD', ''),
        'timeout'    => 15,
    ],

    'from' => [
        'address' => Config::env('MAIL_FROM_ADDRESS', 'no-reply@polishedpro.test'),
        'name'    => Config::env('MAIL_FROM_NAME', Config::env('APP_NAME', 'PolishedPro')),
    ],
];
