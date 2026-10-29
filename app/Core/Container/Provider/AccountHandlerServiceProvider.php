<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
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
        ];
    }
}
