<?php

declare(strict_types=1);

/**
 * Generate an APP_KEY and write it into .env.
 *
 * Usage:  php bin/key.php
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

$root = dirname(__DIR__);
$env  = $root . '/.env';

$key = base64_encode(random_bytes(32));

if (!is_file($env)) {
    if (!is_file($root . '/.env.example')) {
        fwrite(STDERR, "No .env or .env.example found.\n");
        exit(1);
    }
    copy($root . '/.env.example', $env);
    echo "Created .env from .env.example\n";
}

$contents = (string) file_get_contents($env);

$contents = preg_match('/^APP_KEY=.*$/m', $contents) === 1
    ? (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents)
    : rtrim($contents) . "\nAPP_KEY=" . $key . "\n";

file_put_contents($env, $contents);

echo "APP_KEY set.\n";
