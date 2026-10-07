<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Handler\Admin\AdminUserGetHandler;
use App\Handler\Admin\AdminUserPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;
use Psr\Container\ContainerInterface;

final class AdminUserHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            AdminUserGetHandler::class => static function (
                ContainerInterface $container
            ): AdminUserGetHandler {
                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var ErrorController $errorController */
                $errorController = $container->get(ErrorController::class);

                /** @var CsrfTokenInterface $csrf */
                $csrf = $container->get(CsrfTokenInterface::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var AuthCheckerInterface $authChecker */
                $authChecker = $container->get(AuthCheckerInterface::class);

                return new AdminUserGetHandler(
                    $userModel,
                    $responder,
                    $errorController,
                    $csrf,
                    $flash,
                    $authChecker,
                );
            },

            AdminUserPostHandler::class => static function (
                ContainerInterface $container
            ): AdminUserPostHandler {
                /** @var AdminUserServiceInterface $adminUserService */
                $adminUserService = $container->get(
                    AdminUserServiceInterface::class
                );

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var ErrorController $errorController */
                $errorController = $container->get(ErrorController::class);

                return new AdminUserPostHandler(
                    $adminUserService,
                    $flash,
                    $responder,
                    $errorController,
                );
            },
        ];
    }
}
