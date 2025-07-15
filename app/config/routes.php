<?php

use App\Controller\HomeController;
use App\Core\Router;

return [
    Router::METHOD_GET => [
        '/' => [HomeController::class, 'index'],
    ],
];
