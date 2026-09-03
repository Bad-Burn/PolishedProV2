<?php

declare(strict_types=1);

use App\Core\Config;

return [
    // MySQL / MariaDB only. The schema uses ENUM, FK constraints and InnoDB
    // row locking, so there is no second driver to keep in sync.
    'driver' => 'mysql',

    'mysql' => [
        'host'     => Config::env('DB_HOST', '127.0.0.1'),
        'port'     => (int) Config::env('DB_PORT', 3306),
        'database' => Config::env('DB_NAME', 'polishedpro_v2'),
        'username' => Config::env('DB_USER', 'root'),
        'password' => Config::env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
        'collation'=> 'utf8mb4_unicode_ci',
    ],
];
