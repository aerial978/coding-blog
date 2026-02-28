<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use Psr\Container\ContainerInterface;
use App\Core\Contract\FlashInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Model\Contract\UserModelInterface;
use App\Service\Security\LoginService;
use App\Controller\DebugController;
use App\Core\Contract\SessionInterface;
use App\Controller\AccountController;
use App\Controller\RegisterController;            // <-- AJOUT
use App\Handler\Auth\RegisterGetHandler;          // <-- AJOUT
use App\Handler\Auth\RegisterPostHandler;         // <-- AJOUT
use App\Controller\ConfirmAccountController;
use App\Controller\ResendConfirmationController;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Handler\Auth\ResendConfirmationGetHandler;    
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Controller\LoginController;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;
use App\Controller\ForgotPasswordController;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;

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
     * Each entry associates a fully qualified class name (FQCN) of a controller
     * with a closure that receives the container and returns an instantiated controller.
     *
     * @return array<class-string, \Closure(ContainerInterface):object>
     *     Map of [Controller FQCN => factory(ContainerInterface): Controller instance]
     */
    public static function getDefinitions(): array
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

            RegisterController::class => static function (ContainerInterface $container): RegisterController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var RegisterGetHandler $getHandler */
                $getHandler = $container->get(RegisterGetHandler::class);

                /** @var RegisterPostHandler $postHandler */
                $postHandler = $container->get(RegisterPostHandler::class);

                return new RegisterController(
                    $request,
                    $getHandler,
                    $postHandler
                );
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

                return new ResendConfirmationController(
                    $request,
                    $getHandler,
                    $postHandler

                );
            },

            LoginController::class => static function (ContainerInterface $container): LoginController {
                /** @var Request $request */
                $request = $container->get(Request::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var LoginGetHandler $getHandler */
                $getHandler = $container->get(LoginGetHandler::class);

                /** @var LoginPostHandler $postHandler */
                $postHandler = $container->get(LoginPostHandler::class);

                return new LoginController($request, $getHandler, $postHandler);
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

            DebugController::class => static function (ContainerInterface $container): object {
                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new DebugController($session);
            },

            AccountController::class => static function (ContainerInterface $container): object {
            /** @var \App\Core\View $view */
            $view = $container->get(\App\Core\View::class);

            /** @var \App\Core\Contract\FlashInterface $flash */
            $flash = $container->get(\App\Core\Contract\FlashInterface::class);

            return new AccountController($view, $flash);
    },

        ];
    }
}
