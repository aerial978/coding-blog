<?php

use App\Core\Router;
use App\Controller\HomeController;
use App\Controller\SecurityController;

return [
    Router::METHOD_GET => [
        '/' => [HomeController::class, 'index'],
        '/register' => [SecurityController::class, 'register'],
    ],
];
