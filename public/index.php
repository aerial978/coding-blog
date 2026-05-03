<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\AppConfig;
use App\Core\EnvLoader;
use App\Core\ErrorHandler;
use App\Core\Router;
use App\Core\PsrControllerFactory;
use App\Core\Container\AppContainer;
use App\Controller\ErrorController;
use App\Http\Request;

date_default_timezone_set('Europe/Paris');

// 1) Env
EnvLoader::load(__DIR__ . '/../');

// 1.1) Session (centralisée et sécurisée)
session_name('SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2) Conteneur PSR-11 (définitions)
$definitions = require __DIR__ . '/../app/config/services.php';
$psr = new AppContainer($definitions);

// 3) Logger d’erreur via le container PSR-11
ErrorHandler::register($psr->get('logger.error'));

// 4) Affichage des erreurs selon l’environnement
if (AppConfig::isLocal()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 5) Routes
$routes = require __DIR__ . '/../app/config/routes.php';

// 6) basePath depuis la config app
/** @var array{base_path: string} $cfg */
$cfg      = require __DIR__ . '/../app/Config/app.php';
$basePath = rtrim($cfg['base_path'] ?? '', '/');

// 7) Router (avec factory PSR)
$router = new Router(
    $routes,
    $basePath,
    $psr->get(ErrorController::class),
    $psr->get(Request::class),
    new PsrControllerFactory($psr)
);

// 7.1) Middlewares applicatifs
$router->addMiddleware($psr->get(\App\Middleware\SecurityHeadersMiddleware::class));
$router->addMiddleware($psr->get(\App\Middleware\RememberMeMiddleware::class));
$router->addMiddleware($psr->get(\App\Middleware\CsrfMiddleware::class));
$router->addMiddleware($psr->get(\App\Middleware\AuthenticationMiddleware::class));

// 8) Démarrage
$router->handleRequest();
