<?php

use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\Controller\DebugController;
use App\Controller\AccountController;
use App\Controller\RegisterController;
use App\Controller\ConfirmAccountController;
use App\Controller\ResendConfirmationController;
use App\Controller\LoginController;
use App\Core\Router;

return [
    Router::METHOD_GET => [
        '/'                    => [HomeController::class, 'index'],
        '/register'            => [RegisterController::class, 'register'],
        '/confirm-account'     => [ConfirmAccountController::class, 'confirmAccount'],
        '/resend-confirmation' => [ResendConfirmationController::class, 'resendConfirmation'],
        '/login'               => [LoginController::class, 'login'],
        '/forgot-password'     => [SecurityController::class, 'forgotPassword'],
        '/reset-password'      => [SecurityController::class, 'resetPassword'],
        '/debug/whoami'        => [DebugController::class, 'whoami'],
        '/account'             => [AccountController::class, 'index'],
    ],
    Router::METHOD_POST => [
        '/register'            => [RegisterController::class, 'register'],
        '/resend-confirmation' => [ResendConfirmationController::class, 'resendConfirmation'],
        '/login'               => [LoginController::class, 'login'],
        '/logout'              => [SecurityController::class, 'logout'],
        '/forgot-password'     => [SecurityController::class, 'forgotPassword'],
        '/reset-password'      => [SecurityController::class, 'resetPassword'],
    ],
];

