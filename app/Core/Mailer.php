<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Outgoing email over SMTP, written against the raw protocol so the project
 * stays Composer-free — the same choice PayPalClient makes for HTTP.
 *
 * Web requests queue their mail and send it after the response has gone to
 * the browser, so a slow mail server never slows a booking down. From the
 * command line there is no response to wait for, so mail goes immediately.
 *
 * Delivery failures are logged, never thrown: the in-app notification has
 * already been saved, and an email that did not leave must not turn a
 * completed booking into an error page.
 */
final class Mailer
{
    /** @var array<int, array{to:string, name:string, subject:string, html:string, text:string}> */
    private static array $queue = [];

    private static bool $flushRegistered = false;

    public static function queue(string $to, string $name, string $subject, string $html, string $text): void
    {
        $message = [
            'to'      => trim($to),
            'name'    => self::oneLine($name),
            'subject' => self::oneLine($subject),
            'html'    => $html,
            'text'    => $text,
        ];

        if (filter_var($message['to'], FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('Email not sent: invalid recipient', ['subject' => $message['subject']]);
            return;
        }

        if (PHP_SAPI === 'cli') {
            self::deliver($message);
            return;
        }

        self::$queue[] = $message;

        if (!self::$flushRegistered) {
            self::$flushRegistered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** @internal registered as a shutdown function */
    public static function flush(): void
    {
        if (self::$queue === []) {
            return;
        }

        // Hand the browser its response first. The session is closed before
        // that, or the next request would wait on its lock until mail is done.
        if (function_exists('fastcgi_finish_request')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            fastcgi_finish_request();
        }

        ignore_user_abort(true);

        while (($message = array_shift(self::$queue)) !== null) {
            self::deliver($message);
        }
    }

    /** @param array{to:string, name:string, subject:string, html:string, text:string} $message */
    private static function deliver(array $message): void
    {
        $driver = (string) Config::get('mail.driver', 'log');

        try {
            if ($driver === 'smtp') {
                self::sendSmtp($message);
            } else {
                self::writeToLog($message);
            }
        } catch (\Throwable $e) {
            Logger::error('Email delivery failed: ' . $e->getMessage(), [
                'subject' => $message['subject'],
                'to_hash' => substr(hash('sha256', strtolower($message['to'])), 0, 12),
            ]);
        }
    }

    /** Local development: keep the whole message where a developer can read it. */
    private static function writeToLog(array $message): void
    {
        $dir = PP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $entry = sprintf(
            "==== %s\nTo: %s <%s>\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $message['name'],
            $message['to'],
            $message['subject'],
            $message['text']
        );

        file_put_contents($dir . DIRECTORY_SEPARATOR . 'mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    // -- SMTP ---------------------------------------------------------------

    /** @param array{to:string, name:string, subject:string, html:string, text:string} $message */
    private static function sendSmtp(array $message): void
    {
        $config     = (array) Config::get('mail.smtp', []);
        $host       = (string) ($config['host'] ?? '');
        $port       = (int) ($config['port'] ?? 587);
        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        $timeout    = (int) ($config['timeout'] ?? 15);
        $from       = (string) Config::get('mail.from.address', '');

        if ($host === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('MAIL_HOST and MAIL_FROM_ADDRESS must be set for the smtp driver.');
        }

        // Certificate checks stay on: credentials cross this connection.
        $context = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'peer_name'        => $host,
        ]]);

        $socket = @stream_socket_client(
            ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new \RuntimeException("Could not connect to {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, $timeout);

        try {
            $ehloName = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost');

            self::expect($socket, [220]);
            self::command($socket, 'EHLO ' . $ehloName, [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);

                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (stream_socket_enable_crypto($socket, true, $method) !== true) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }

                self::command($socket, 'EHLO ' . $ehloName, [250]);
            }

            $username = (string) ($config['username'] ?? '');
            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334], true);
                self::command($socket, base64_encode((string) ($config['password'] ?? '')), [235], true);
            }

            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $message['to'] . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            // Dot-stuffing: a line that starts with "." would otherwise end DATA.
            $body = preg_replace('/^\./m', '..', self::compose($message, $from)) ?? '';
            self::command($socket, $body . "\r\n.", [250]);

            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private static function command($socket, string $line, array $expected, bool $secret = false): void
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new \RuntimeException('Lost the connection to the mail server');
        }

        self::expect($socket, $expected, $secret ? '[credentials]' : strtok($line, "\r\n"));
    }

    /** @param resource $socket */
    private static function expect($socket, array $expected, string $after = 'connect'): void
    {
        $response = '';

        // Multi-line replies use "250-" on every line but the last ("250 ").
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expected, true)) {
            // Never echo AUTH lines back into the log.
            $context = str_starts_with($after, 'MAIL FROM') || str_starts_with($after, 'RCPT TO') ? strtok($after, ':') : $after;
            throw new \RuntimeException(sprintf('SMTP %s after %s: %s', $code ?: 'timeout', $context, trim($response)));
        }
    }

    /** @param array{to:string, name:string, subject:string, html:string, text:string} $message */
    private static function compose(array $message, string $from): string
    {
        $boundary = 'pp-' . bin2hex(random_bytes(12));
        $domain   = substr((string) strrchr($from, '@'), 1) ?: 'localhost';
        $fromName = self::oneLine((string) Config::get('mail.from.name', 'PolishedPro'));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::address($from, $fromName),
            'To: ' . self::address($message['to'], $message['name']),
            'Subject: ' . mb_encode_mimeheader($message['subject'], 'UTF-8', 'B', "\r\n"),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            // Transactional: ask auto-responders not to reply.
            'Auto-Submitted: auto-generated',
        ];

        $part = static fn (string $type, string $content): string =>
            "--{$boundary}\r\n"
            . "Content-Type: {$type}; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode($content), 76, "\r\n")) . "\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n"
            . $part('text/plain', $message['text'])
            . $part('text/html', $message['html'])
            . "--{$boundary}--";
    }

    private static function address(string $email, string $name): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }

        // Plain ASCII goes in a quoted string, so a name containing ":" or "@"
        // stays a name; anything else is MIME-encoded.
        if (preg_match('/^[\x20-\x7E]*$/', $name) === 1) {
            return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
        }

        return mb_encode_mimeheader($name, 'UTF-8', 'B', "\r\n") . ' <' . $email . '>';
    }

    /** Header values must never carry a line break — that is header injection. */
    private static function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
