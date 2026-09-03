<?php

declare(strict_types=1);

/**
 * Schema migrator.
 *
 *   php bin/migrate.php           run any pending migrations
 *   php bin/migrate.php --fresh   DROP every table, then run them all again
 *   php bin/migrate.php --status  list applied / pending files
 *
 * Migrations are plain .sql files in database/migrations, applied in filename
 * order and recorded in a `migrations` table so re-running is safe.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Core\Config;

$flags   = array_slice($argv, 1);
$fresh   = in_array('--fresh', $flags, true);
$status  = in_array('--status', $flags, true);

$dir   = PP_ROOT . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

if ($files === []) {
    cli_error("No migration files found in {$dir}");
    exit(1);
}

// Create the database if the configured user is allowed to.
ensure_database_exists();

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename   VARCHAR(190) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_migration_file (filename)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = array_column(
    Database::select('SELECT filename FROM migrations'),
    'filename'
);

if ($status) {
    cli_heading('Migration status');
    foreach ($files as $file) {
        $name = basename($file);
        cli_line(sprintf('  %s  %s', in_array($name, $applied, true) ? '[applied]' : '[pending]', $name));
    }
    exit(0);
}

if ($fresh) {
    cli_warn('--fresh will DROP every table in `' . Config::get('database.mysql.database') . '`.');
    if (!cli_confirm('Type "yes" to continue: ')) {
        cli_line('Aborted. Nothing was changed.');
        exit(0);
    }
    drop_all_tables($pdo);
    $applied = [];
    cli_ok('All tables dropped.');
}

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    $sql = (string) file_get_contents($file);

    try {
        // MySQL DDL is not transactional, so a failure here is reported loudly
        // rather than silently half-applied.
        foreach (split_sql_statements($sql) as $statement) {
            $pdo->exec($statement);
        }

        Database::insert('INSERT INTO migrations (filename) VALUES (:f)', ['f' => $name]);
        cli_ok("Applied {$name}");
        $ran++;
    } catch (Throwable $e) {
        cli_error("Failed on {$name}: " . $e->getMessage());
        exit(1);
    }
}

cli_line('');
cli_ok($ran === 0 ? 'Database already up to date.' : "Done — {$ran} migration(s) applied.");

// ---------------------------------------------------------------------------

/**
 * Connect without a database name so we can CREATE DATABASE on first run.
 */
function ensure_database_exists(): void
{
    $config = (array) Config::get('database.mysql');
    $name   = (string) $config['database'];

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], (int) $config['port']),
            (string) $config['username'],
            (string) $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // The name comes from .env, not from a request, but it is still
        // validated and back-quoted rather than interpolated blindly.
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            cli_error("Invalid DB_NAME '{$name}'. Use letters, numbers and underscores only.");
            exit(1);
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // Not fatal: the database may already exist and the user may simply
        // lack CREATE DATABASE rights, which is normal on shared hosting.
        cli_warn('Could not auto-create the database (' . $e->getMessage() . ')');
        cli_warn('Continuing — make sure `' . $name . '` exists.');
    }
}

function drop_all_tables(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/**
 * Split a .sql file on semicolons, ignoring those inside quotes or comments.
 *
 * @return array<int, string>
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $inSingle   = false;
    $inDouble   = false;
    $inComment  = false;
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($inComment) {
            if ($char === "\n") {
                $inComment = false;
                $buffer .= $char;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
            $inComment = true;
            $i++;
            continue;
        }

        if ($char === "'" && !$inDouble && ($sql[$i - 1] ?? '') !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && ($sql[$i - 1] ?? '') !== '\\') {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}
