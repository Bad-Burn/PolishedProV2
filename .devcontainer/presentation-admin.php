<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Models\User;

require dirname(__DIR__) . '/bin/_cli.php';

if (Config::get('database.mysql.database') !== 'polishedpro_presentation') {
    cli_error('The generated administrator is only for the presentation database.');
    exit(1);
}

$email = 'demo-admin@polishedpro.test';
$credentials = PP_ROOT . '/storage/codespaces/admin-password';
$existing = User::findByEmail($email);

if ($existing !== null) {
    if ($existing['role'] !== 'admin') {
        cli_error('The presentation administrator email is already in use by another account.');
        exit(1);
    }
    exit(0);
}

if (!is_file($credentials)) {
    $password = bin2hex(random_bytes(18));
    if (file_put_contents($credentials, $password . PHP_EOL, LOCK_EX) === false) {
        cli_error('Could not save the presentation administrator password.');
        exit(1);
    }
    chmod($credentials, 0600);
}
$password = trim((string) file_get_contents($credentials));
if (strlen($password) < 10) {
    cli_error('The saved presentation administrator password is incomplete.');
    exit(1);
}

Database::transaction(static function () use ($email, $password): void {
    $id = User::create([
        'role' => 'admin',
        'full_name' => 'Demo Administrator',
        'email' => $email,
        'password' => $password,
    ]);
    User::update($id, ['email_verified_at' => date('Y-m-d H:i:s')]);
});

cli_ok('Presentation administrator created. Its password is in storage/codespaces/admin-password.');
