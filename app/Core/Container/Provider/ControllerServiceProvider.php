<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Controller\AccountController;
use App\Controller\ConfirmAccountController;
use App\Controller\DebugController;
use App\Controller\Email2faController;
use App\Controller\ErrorController;
use App\Controller\ForgotPasswordController;
use App\Controller\GoogleOAuthController;
use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\LogoutController;
use App\Controller\RegisterController;
use App\Controller\ResendConfirmationController;
use App\Controller\ResetPasswordController;
use App\Core\Contract\SessionInterface;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;
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
use App\Handler\OAuth\GoogleOAuthCallbackHandler;
use App\Handler\OAuth\GoogleOAuthStartHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use Psr\Container\ContainerInterface;
use App\Service\Account\Contract\AccountServiceInterface;

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
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var AuthCheckerInterface $authChecker */
                $authChecker = $container->get(AuthCheckerInterface::class);

                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new HomeController($userModel, $authChecker, $csrf, $responder);
            },

            ErrorController::class => static function (ContainerInterface $container): ErrorController {
                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new ErrorController($responder);
            },

            AccountController::class => static function (ContainerInterface $container): AccountController {
                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var AccountServiceInterface $accountService */
                $accountService = $container->get(AccountServiceInterface::class);

                return new AccountController(
                    $csrf,
                    $responder,
                    $accountService,
                );
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getAuthControllerDefinitions(): array
    {
        return array_merge(
            self::getRegistrationControllerDefinitions(),
            self::getLoginControllerDefinitions(),
            self::getGoogleOAuthControllerDefinitions(),
            self::getPasswordControllerDefinitions(),
            self::getEmail2faControllerDefinitions(),
            self::getLogoutControllerDefinitions(),
        );
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

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getRegistrationControllerDefinitions(): array
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
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getLoginControllerDefinitions(): array
    {
        return [
            LoginController::class => static function (ContainerInterface $container): LoginController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var LoginGetHandler $getHandler */
                $getHandler = $container->get(LoginGetHandler::class);

                /** @var LoginPostHandler $postHandler */
                $postHandler = $container->get(LoginPostHandler::class);

                return new LoginController($request, $getHandler, $postHandler);
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getGoogleOAuthControllerDefinitions(): array
    {
        return [
            GoogleOAuthController::class => static function (
                ContainerInterface $container
            ): GoogleOAuthController {
                /** @var GoogleOAuthStartHandler $startHandler */
                $startHandler = $container->get(
                    GoogleOAuthStartHandler::class
                );

                /** @var GoogleOAuthCallbackHandler $callbackHandler */
                $callbackHandler = $container->get(
                    GoogleOAuthCallbackHandler::class
                );

                /** @var Request $request */
                $request = $container->get(Request::class);

                return new GoogleOAuthController(
                    $startHandler,
                    $callbackHandler,
                    $request,
                );
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getPasswordControllerDefinitions(): array
    {
        return [
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
    private static function getEmail2faControllerDefinitions(): array
    {
        return [
            Email2faController::class => static function (ContainerInterface $container): Email2faController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var Email2faGetHandler $getHandler */
                $getHandler = $container->get(Email2faGetHandler::class);

                /** @var Email2faPostHandler $postHandler */
                $postHandler = $container->get(Email2faPostHandler::class);

                /** @var Email2faResendPostHandler $resendPostHandler */
                $resendPostHandler = $container->get(Email2faResendPostHandler::class);

                return new Email2faController(
                    $request,
                    $getHandler,
                    $postHandler,
                    $resendPostHandler,
                );
            },
        ];
    }

    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    private static function getLogoutControllerDefinitions(): array
    {
        return [
            LogoutController::class => static function (ContainerInterface $container): LogoutController {
                /** @var LogoutHandler $handler */
                $handler = $container->get(LogoutHandler::class);

                return new LogoutController($handler);
            },
        ];
    }
}
