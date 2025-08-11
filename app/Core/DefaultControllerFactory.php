<?php

namespace App\Core;

use App\Controller\ErrorController;
use App\Controller\HomeController;

/**
 * Default implementation of the ControllerFactoryInterface.
 *
 * This factory is responsible for creating controller instances.
 * It uses the Container to resolve and inject the required dependencies.
 */
class DefaultControllerFactory implements ControllerFactoryInterface
{
    /**
     * @param Container $container The main service container used to get controllers and their dependencies.
     */
    public function __construct(private Container $container)
    {
    }

    /**
     * Creates and returns an instance of the requested controller.
     *
     * Uses a match expression to determine which controller to return.
     * Falls back to directly instantiating the class if not explicitly handled.
     *
     * @param string $controllerClass The fully-qualified class name of the controller.
     * @return object The instantiated controller.
     */
    public function create(string $controllerClass): object
    {
        return match ($controllerClass) {
            HomeController::class  => $this->container->getHomeController(),
            ErrorController::class => $this->container->getErrorController(),
            default                => new $controllerClass(),
        };
    }
}
