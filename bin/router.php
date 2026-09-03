<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in web server, for local work without XAMPP:
 *
 *   php -S 127.0.0.1:8000 -t public bin/router.php
 *
 * It applies the same rule public/.htaccess gives Apache — serve a file that
 * exists, hand everything else to the front controller. Without it the built-in
 * server sends CSS, JS and uploaded images through index.php and they 404.
 *
 * It lives outside public/ so it is never reachable over HTTP in production.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';
