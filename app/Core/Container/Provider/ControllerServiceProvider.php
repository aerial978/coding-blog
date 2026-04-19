<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Controller\AccountController;
use App\Controller\ConfirmAccountController;
use App\Controller\DebugController;
use App\Controller\ErrorController;
use App\Controller\ForgotPasswordController;
use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\LogoutController;
use App\Controller\RegisterController;
use App\Controller\ResendConfirmationController;
use App\Controller\ResetPasswordController;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\View;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Handler\Auth\LogoutHandler;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\CsrfTokenInterface;
use Psr\Container\ContainerInterface;

/**
 * Provides controller definitions for the dependency injection container.
 *
 * This service provider maps each controller class to a factory closure
 * responsible for creating and wiring the controller’s dependencies.
 *
 * The class is part of the custom dependency injection setup that replaces
 * the need for a full framework container (e.g., Symfony’s or Laravel’s).
 */
final class ControllerServiceProvider
{
    /**
     * Returns an array of controller definitions for the container.
     *
     * @return array<class-string, \Closure(ContainerInterface):object>
     *     Map of [Controller FQCN => factory(ContainerInterface): Controller instance]
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            self::getCoreControllerDefinitions(),
            self::getAuthControllerDefinitions(),
            self::getUtilityControllerDefinitions(),
        );
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getCoreControllerDefinitions(): array
    {
        return [
            HomeController::class => static function (ContainerInterface $container): HomeController {
                /** @var View $view */
                $view = $container->get(View::class);

                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                return new HomeController($view, $userModel, $flash);
            },

            ErrorController::class => static function (ContainerInterface $container): ErrorController {
                /** @var View $view */
                $view = $container->get(View::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                return new ErrorController($view, $flash);
            },

            AccountController::class => static function (ContainerInterface $container): AccountController {
                /** @var View $view */
                $view = $container->get(View::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                return new AccountController($view, $flash, $csrf);
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getAuthControllerDefinitions(): array
    {
        return [
            RegisterController::class => static function (ContainerInterface $container): RegisterController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var RegisterGetHandler $getHandler */
                $getHandler = $container->get(RegisterGetHandler::class);

                /** @var RegisterPostHandler $postHandler */
                $postHandler = $container->get(RegisterPostHandler::class);

                return new RegisterController($request, $getHandler, $postHandler);
            },

            ConfirmAccountController::class => static function (ContainerInterface $container): ConfirmAccountController {
                /** @var ConfirmAccountHandler $handler */
                $handler = $container->get(ConfirmAccountHandler::class);

                return new ConfirmAccountController($handler);
            },

            ResendConfirmationController::class => static function (ContainerInterface $container): ResendConfirmationController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var ResendConfirmationGetHandler $getHandler */
                $getHandler = $container->get(ResendConfirmationGetHandler::class);

                /** @var ResendConfirmationPostHandler $postHandler */
                $postHandler = $container->get(ResendConfirmationPostHandler::class);

                return new ResendConfirmationController($request, $getHandler, $postHandler);
            },

            LoginController::class => static function (ContainerInterface $container): LoginController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var LoginGetHandler $getHandler */
                $getHandler = $container->get(LoginGetHandler::class);

                /** @var LoginPostHandler $postHandler */
                $postHandler = $container->get(LoginPostHandler::class);

                return new LoginController($request, $getHandler, $postHandler);
            },

            LogoutController::class => static function (ContainerInterface $container): LogoutController {
                /** @var LogoutHandler $handler */
                $handler = $container->get(LogoutHandler::class);

                return new LogoutController($handler);
            },

            ForgotPasswordController::class => static function (ContainerInterface $container): ForgotPasswordController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var ForgotPasswordGetHandler $getHandler */
                $getHandler = $container->get(ForgotPasswordGetHandler::class);

                /** @var ForgotPasswordPostHandler $postHandler */
                $postHandler = $container->get(ForgotPasswordPostHandler::class);

                return new ForgotPasswordController($request, $getHandler, $postHandler);
            },

            ResetPasswordController::class => static function (ContainerInterface $container): ResetPasswordController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var ResetPasswordGetHandler $getHandler */
                $getHandler = $container->get(ResetPasswordGetHandler::class);

                /** @var ResetPasswordPostHandler $postHandler */
                $postHandler = $container->get(ResetPasswordPostHandler::class);

                return new ResetPasswordController($request, $getHandler, $postHandler);
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getUtilityControllerDefinitions(): array
    {
        return [
            DebugController::class => static function (ContainerInterface $container): DebugController {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new DebugController($session);
            },
        ];
    }
}
