<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Handler\Account\AccountPasswordChangeHandler;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Service\Account\Contract\AccountPasswordChangeServiceInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;
use Psr\Container\ContainerInterface;

final class AccountHandlerServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            AccountUsernameUpdateHandler::class => static function (
                ContainerInterface $container
            ): AccountUsernameUpdateHandler {
                /** @var AccountUsernameUpdateServiceInterface $updateService */
                $updateService = $container->get(
                    AccountUsernameUpdateServiceInterface::class
                );

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                return new AccountUsernameUpdateHandler(
                    $updateService,
                    $flash,
                    $responder,
                );
            },

            AccountPasswordChangeHandler::class => static function (
                ContainerInterface $container
            ): AccountPasswordChangeHandler {
                /** @var AccountPasswordChangeServiceInterface $passwordService */
                $passwordService = $container->get(
                    AccountPasswordChangeServiceInterface::class
                );

                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var ResponderInterface $responder */
                $responder = $container->get(ResponderInterface::class);

                /** @var RememberMeCookieManagerInterface $cookieManager */
                $cookieManager = $container->get(
                    RememberMeCookieManagerInterface::class
                );

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                /** @var RateLimitGuardInterface $rateLimitGuard */
                $rateLimitGuard = $container->get(
                    RateLimitGuardInterface::class
                );

                return new AccountPasswordChangeHandler(
                    $passwordService,
                    $flash,
                    $responder,
                    $cookieManager,
                    $session,
                    $rateLimitGuard,
                );
            },
        ];
    }
}
