<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\LogoutHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use Psr\Container\ContainerInterface;

final class LogoutHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            LogoutHandler::class => static function (
                ContainerInterface $container
            ): LogoutHandler {
                /** @var SecurityServiceInterface $securityService */
                $securityService = $container->get(SecurityServiceInterface::class);

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var RememberMeCookieManagerInterface $rememberMeManager */
                $rememberMeManager = $container->get(
                    RememberMeCookieManagerInterface::class
                );

                return new LogoutHandler(
                    $securityService,
                    $flash,
                    $responder,
                    $rememberMeManager,
                );
            },
        ];
    }
}
