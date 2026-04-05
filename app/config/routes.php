<?php

use App\Controller\AccountController;
use App\Controller\ConfirmAccountController;
use App\Controller\DebugController;
use App\Controller\ForgotPasswordController;
use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\RegisterController;
use App\Controller\ResendConfirmationController;
use App\Controller\ResetPasswordController;
use App\Core\Router;

return [
    Router::METHOD_GET => [
        '/'                    => [HomeController::class, 'index'],
        '/register'            => [RegisterController::class, 'register'],
        '/confirm-account'     => [ConfirmAccountController::class, 'confirmAccount'],
        '/resend-confirmation' => [ResendConfirmationController::class, 'resendConfirmation'],
        '/login'               => [LoginController::class, 'login'],
        '/forgot-password'     => [ForgotPasswordController::class, 'forgotPassword'],
        '/reset-password'      => [ResetPasswordController::class, 'resetPassword'],
        '/debug/whoami'        => [DebugController::class, 'whoami'],
        '/account'             => [AccountController::class, 'index'],
    ],
    Router::METHOD_POST => [
        '/register'            => [RegisterController::class, 'register'],
        '/resend-confirmation' => [ResendConfirmationController::class, 'resendConfirmation'],
        '/login'               => [LoginController::class, 'login'],
        '/forgot-password'     => [ForgotPasswordController::class, 'forgotPassword'],
        '/reset-password'      => [ResetPasswordController::class, 'resetPassword'],
    ],
];
