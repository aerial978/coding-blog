<?php

namespace App\Core;

use Psr\Container\ContainerInterface;

final class PsrControllerFactory implements ControllerFactoryInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function create(string $controllerClass): object
    {
        return $this->container->get($controllerClass);
    }
}
