<?php

declare(strict_types=1);

/**
 * Seed reference data and (optionally) a demo dataset.
 *
 *   php bin/seed.php              reference data + demo accounts
 *   php bin/seed.php --reference  categories, plans and settings only
 *
 * Use --reference when deploying for real: it installs the data the app needs
 * to function without inventing fake artists.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';
require PP_ROOT . '/database/seeds/DemoSeeder.php';

use Database\Seeds\DemoSeeder;

$referenceOnly = in_array('--reference', array_slice($argv, 1), true);

cli_heading($referenceOnly ? 'Seeding reference data' : 'Seeding database');

try {
    (new DemoSeeder())->run(!$referenceOnly);
} catch (Throwable $e) {
    cli_error($e->getMessage());
    cli_line('Have you run `php bin/migrate.php` first?');
    exit(1);
}

cli_line('');

if (!$referenceOnly) {
    cli_heading('Demo sign-ins');
    cli_line('  Artist    mariel@polishedpro.test');
    cli_line('  Artist    jonas@polishedpro.test');
    cli_line('  Customer  bea@example.test');
    cli_line('  Password  ' . DemoSeeder::DEMO_PASSWORD);
    cli_line('');
    cli_warn('Demo accounts share one password. Delete them before going live.');
    cli_line('');
}

cli_line('Create your admin account with:  php bin/admin.php');
