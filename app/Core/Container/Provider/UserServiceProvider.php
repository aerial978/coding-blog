<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use Psr\Container\ContainerInterface;
use App\Model\UserModel;
use App\Core\SqlHelper;

final class UserServiceProvider
{
    public static function getDefinitions(): array
    {
        return [
            UserModel::class => fn (ContainerInterface $c) =>
            new UserModel($c->get(SqlHelper::class), $c->get('logger.app')),
        ];

    }

}