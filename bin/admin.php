<?php

declare(strict_types=1);

/**
 * Create or update the platform administrator.
 *
 *   php bin/admin.php
 *
 * This is deliberately CLI-only. The old system exposed an `admin-setup.php`
 * page on the public web root and relied on the operator remembering to delete
 * it — anyone who found it first owned the platform. An account that can only
 * be created from a shell has no such window.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\Database;
use App\Core\Hash;
use App\Core\Validator;

cli_heading('PolishedPro — administrator setup');

$name = cli_ask('Full name: ');
if ($name === '') {
    cli_error('A name is required.');
    exit(1);
}

$email = strtolower(cli_ask('Email: '));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    cli_error('That is not a valid email address.');
    exit(1);
}

$password = cli_ask_hidden('Password (min 10 characters): ');
$confirm  = cli_ask_hidden('Confirm password: ');

if ($password !== $confirm) {
    cli_error('The passwords do not match.');
    exit(1);
}

// Same policy the web form enforces — no weaker path in through the back door.
$validator = Validator::make(
    ['email' => $email, 'password' => $password],
    ['password' => 'required|password']
);

if ($validator->fails()) {
    foreach ($validator->errors() as $message) {
        cli_error($message);
    }
    exit(1);
}

try {
    $existing = Database::selectOne('SELECT id, role FROM users WHERE email = :e', ['e' => $email]);

    if ($existing !== null) {
        cli_warn("An account already exists for {$email} (role: {$existing['role']}).");
        if (!cli_confirm('Promote it to admin and reset its password? Type "yes": ')) {
            cli_line('Aborted.');
            exit(0);
        }

        Database::affectingStatement(
            'UPDATE users
                SET role = "admin", full_name = :n, password_hash = :h,
                    status = "active", approved_at = NOW(), email_verified_at = NOW()
              WHERE id = :id',
            ['n' => $name, 'h' => Hash::make($password), 'id' => $existing['id']]
        );

        cli_ok('Account updated and promoted to admin.');
    } else {
        Database::insert(
            'INSERT INTO users (role, full_name, email, password_hash, status, approved_at, email_verified_at)
             VALUES ("admin", :n, :e, :h, "active", NOW(), NOW())',
            ['n' => $name, 'e' => $email, 'h' => Hash::make($password)]
        );

        cli_ok('Administrator created.');
    }
} catch (Throwable $e) {
    cli_error($e->getMessage());
    cli_line('Have you run `php bin/migrate.php` first?');
    exit(1);
}

cli_line('');
cli_line('Sign in at ' . (App\Core\Config::get('app.url') ?: 'your site') . '/login');
cli_line('');
cli_warn('Turn on two-factor authentication from Admin → Security once you are in.');

// ---------------------------------------------------------------------------

function cli_ask(string $prompt): string
{
    echo $prompt;
    return trim((string) fgets(STDIN));
}

/**
 * Read without echoing. Falls back to a visible prompt with a warning on
 * terminals that cannot hide input (plain cmd.exe).
 */
function cli_ask_hidden(string $prompt): string
{
    if (DIRECTORY_SEPARATOR !== '\\' && @exec('which stty') !== '') {
        echo $prompt;
        @exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        @exec('stty echo');
        echo PHP_EOL;
        return $value;
    }

    // PowerShell can hide input even though PHP on Windows cannot.
    if (DIRECTORY_SEPARATOR === '\\') {
        $command = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString \'' . addslashes(rtrim($prompt, ': ')) . '\';'
            . ' [Runtime.InteropServices.Marshal]::PtrToStringAuto('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';

        $value = @shell_exec($command);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    cli_warn('This terminal cannot hide input — your password will be visible.');
    echo $prompt;
    return trim((string) fgets(STDIN));
}
