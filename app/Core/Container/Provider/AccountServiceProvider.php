<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\AccountService;
use App\Service\Account\Contract\AccountServiceInterface;
use Psr\Container\ContainerInterface;

final class AccountServiceProvider
{
    /**
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return [
            AccountService::class => static function (
                ContainerInterface $container
            ): AccountService {
                /** @var AuthCheckerInterface $authChecker */
                $authChecker = $container->get(AuthCheckerInterface::class);

                /** @var UserModelInterface $userModel */
                $userModel = $container->get(UserModelInterface::class);

                return new AccountService(
                    $authChecker,
                    $userModel,
                );
            },

            AccountServiceInterface::class => static function (
                ContainerInterface $container
            ): AccountServiceInterface {
                /** @var AccountServiceInterface $accountService */
                $accountService = $container->get(AccountService::class);

                return $accountService;
            },
        ];
    }
}
