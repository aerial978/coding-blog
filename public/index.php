<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\EnvLoader;
use App\Core\ErrorHandler;
use App\Core\AppConfig;
use App\Core\Router;
use App\Core\Database;
use App\Core\Container;

EnvLoader::load(__DIR__ . '/../');

$container = new Container();

ErrorHandler::register($container->getSystem()->getLogger('error'));

// Gestion des erreurs selon l'environnement
if (AppConfig::isLocal()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Chargement des routes
$routes = require __DIR__ . '/../app/config/routes.php';

// Création manuelle d’un PDO à injecter dans toute l’application
$database = new Database();
$pdo = $database->getConnection();

$router = new Router(
    require __DIR__ . '/../app/config/routes.php',
    $container,
    $container->getControllers()->getErrorController(),
    $container->getRequest()
);

$router->handleRequest();
