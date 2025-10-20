<?php

use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\Core\Router;

return [
    Router::METHOD_GET => [
        '/'                    => [HomeController::class, 'index'],
        '/register'            => [SecurityController::class, 'register'],
        '/confirm-account'     => [SecurityController::class, 'confirmAccount'],
        '/resend-confirmation' => [SecurityController::class, 'resendConfirmation'],
    ],
    Router::METHOD_POST => [
        '/register'            => [SecurityController::class, 'register'],
        '/resend-confirmation' => [SecurityController::class, 'resendConfirmation'],
    ],
];
