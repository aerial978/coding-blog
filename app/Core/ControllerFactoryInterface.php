<?php

namespace App\Core;

/**
 * Interface for controller factories.
 *
 * Defines the contract for creating controller instances dynamically
 * based on the provided fully qualified class name.
 */
interface ControllerFactoryInterface
{
    /**
     * Returns an instance of the requested controller.
     *
     * @param class-string $controllerClass Fully qualified class name of the controller.
     * @return object An instance of the controller (ideally strongly typed in an extended version).
     */
    public function create(string $controllerClass): object;
}
