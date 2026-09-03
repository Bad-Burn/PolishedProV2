<?php

declare(strict_types=1);

/**
 * Shared bootstrap + output helpers for the bin/ scripts.
 */

define('PP_ROOT', dirname(__DIR__));
define('PP_PUBLIC', PP_ROOT . DIRECTORY_SEPARATOR . 'public');
define('PP_APP', PP_ROOT . DIRECTORY_SEPARATOR . 'app');
define('PP_VIEWS', PP_APP . DIRECTORY_SEPARATOR . 'Views');

require PP_APP . '/Core/Autoloader.php';

App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', PP_APP);

require PP_APP . '/Support/helpers.php';

App\Core\Config::loadEnv(PP_ROOT . '/.env');
App\Core\Config::load(PP_ROOT . '/config');

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Asia/Manila'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', '1');

/** True when the terminal understands ANSI colour. */
function cli_supports_color(): bool
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return getenv('ANSICON') !== false
            || getenv('WT_SESSION') !== false
            || getenv('TERM_PROGRAM') === 'vscode'
            || getenv('TERM') !== false;
    }
    return function_exists('posix_isatty') && @posix_isatty(STDOUT);
}

function cli_paint(string $text, string $code): string
{
    return cli_supports_color() ? "\033[{$code}m{$text}\033[0m" : $text;
}

function cli_line(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function cli_ok(string $message): void
{
    cli_line(cli_paint('  ok  ', '42;30') . ' ' . $message);
}

function cli_warn(string $message): void
{
    cli_line(cli_paint(' warn ', '43;30') . ' ' . $message);
}

function cli_error(string $message): void
{
    fwrite(STDERR, cli_paint(' fail ', '41;37') . ' ' . $message . PHP_EOL);
}

function cli_heading(string $title): void
{
    cli_line();
    cli_line(cli_paint($title, '1;36'));
    cli_line(cli_paint(str_repeat('-', max(8, mb_strlen($title))), '2;36'));
}

function cli_confirm(string $prompt): bool
{
    echo $prompt;
    $answer = trim((string) fgets(STDIN));
    return strtolower($answer) === 'yes' || strtolower($answer) === 'y';
}
