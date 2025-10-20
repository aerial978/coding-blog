<?php

namespace App\Core;

use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Factory responsible for creating controller instances using a PSR-11 container.
 *
 * This factory delegates the instantiation of controllers to a dependency
 * injection container, ensuring that all controller dependencies are properly
 * resolved and injected at runtime.
 *
 * It provides a thin abstraction that allows the application to remain
 * container-agnostic while still benefiting from dependency injection.
 */
final class PsrControllerFactory implements ControllerFactoryInterface
{
    /**
     * Constructor.
     *
     * Initializes the factory with a PSR-11 compliant container instance.
     *
     * @param ContainerInterface $container
     *     The container responsible for resolving controller dependencies.
     */
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * Creates and returns a controller instance.
     *
     * The controller class must be registered in the container. The factory
     * simply delegates instantiation to the container’s `get()` method.
     *
     * @param string $controllerClass
     *     Fully qualified class name (FQCN) of the controller to instantiate.
     *
     * @return object
     *     The instantiated controller object.
     */
    public function create(string $controllerClass): object
    {
        $controller = $this->container->get($controllerClass);

        if (!is_object($controller)) {
            throw new RuntimeException(sprintf(
                'Controller "%s" must be an object, got %s.',
                $controllerClass,
                gettype($controller)
            ));
        }

        return $controller;
    }
}
