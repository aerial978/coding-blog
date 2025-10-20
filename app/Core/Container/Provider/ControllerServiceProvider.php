<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Controller\SecurityController;
use App\Core\Factory\RateLimiterFactory;
use App\Core\FlashService;
use App\Core\View;
use App\Http\Request;
use App\Model\UserModel;
use App\Security\CsrfTokenManager;
use App\Service\SecurityService;
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
     * Each entry associates a fully qualified class name (FQCN) of a controller
     * with a closure that receives the container and returns an instantiated controller.
     *
     * @return array<class-string, \Closure(ContainerInterface):object>
     *     Map of [Controller FQCN => factory(ContainerInterface): Controller instance]
     */
    public static function getDefinitions(): array
    {
        return [
            HomeController::class => static function (ContainerInterface $container): object {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var UserModel $userModel */
                $userModel = $container->get(UserModel::class);
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);

                return new HomeController($view, $userModel, $flash);
            },

            ErrorController::class => static function (ContainerInterface $container): object {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);

                return new ErrorController($view, $flash);
            },

            SecurityController::class => static function (ContainerInterface $container): object {
                /** @var View $view */
                $view = $container->get(View::class);
                /** @var SecurityService $securityService */
                $securityService = $container->get(SecurityService::class);
                /** @var Request $request */
                $request = $container->get(Request::class);
                /** @var FlashService $flash */
                $flash = $container->get(FlashService::class);
                /** @var CsrfTokenManager $csrf */
                $csrf = $container->get(CsrfTokenManager::class);
                /** @var RateLimiterFactory $rateLimiterFactory */
                $rateLimiterFactory = $container->get(RateLimiterFactory::class);

                return new SecurityController(
                    $view,
                    $securityService,
                    $request,
                    $flash,
                    $csrf,
                    $rateLimiterFactory
                );
            },
        ];
    }
}
