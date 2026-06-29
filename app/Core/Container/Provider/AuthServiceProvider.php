<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

final class AuthServiceProvider
{
    /**
     * @return array<string, callable(\Psr\Container\ContainerInterface): mixed>
     */
    public static function getDefinitions(): array
    {
        return array_merge(
            AuthGuardServiceProvider::getDefinitions(),
            Email2faServiceProvider::getDefinitions(),
            GoogleOAuthServiceProvider::getDefinitions(),
            RegisterHandlerServiceProvider::getDefinitions(),
            LoginHandlerServiceProvider::getDefinitions(),
            Email2faHandlerServiceProvider::getDefinitions(),
            PasswordRecoveryHandlerServiceProvider::getDefinitions(),
            LogoutHandlerServiceProvider::getDefinitions(),
        );
    }
}
