<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use Psr\Container\ContainerInterface;
use App\Controller\HomeController;
use App\Controller\ErrorController;
use App\Controller\SecurityController;
use App\Model\UserModel;

final class ControllerServiceProvider
{
    public static function getDefinitions(): array
    {
        return [
            HomeController::class     => fn (ContainerInterface $c) => new HomeController($c->get(UserModel::class)),
            ErrorController::class   => fn () => new ErrorController(),
            SecurityController::class => fn () => new SecurityController(),
        ];
    }

}