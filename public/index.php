<?php

declare(strict_types=1);

/**
 * PolishedPro — single entry point.
 *
 * Everything the browser can reach lives under public/. Application code,
 * configuration and the .env file sit one level up and are unreachable over
 * HTTP even if the web server is misconfigured to ignore .htaccess.
 */

define('PP_START', microtime(true));
define('PP_PUBLIC', __DIR__);
define('PP_ROOT', dirname(__DIR__));
define('PP_APP', PP_ROOT . DIRECTORY_SEPARATOR . 'app');
define('PP_VIEWS', PP_APP . DIRECTORY_SEPARATOR . 'Views');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PolishedPro requires PHP 8.1 or newer. This server runs ' . PHP_VERSION . '.');
}

require PP_APP . '/Core/Autoloader.php';

App\Core\Autoloader::register();
App\Core\Autoloader::addNamespace('App', PP_APP);

require PP_APP . '/Support/helpers.php';

App\Core\Kernel::boot();

$router = new App\Core\Router();
require PP_ROOT . '/routes/web.php';

App\Core\Kernel::handle(new App\Core\Request(), $router);
