<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;

$routes = require __DIR__ . '/../app/config/routes.php';

$router = new Router($routes);
$router->handleRequest();
