<?php

namespace App\Core\Container;

use App\Controller\ErrorController;
use App\Controller\HomeController;

/**
 * Provides factory methods to create controller instances.
 *
 * This class centralizes the creation of controllers, injecting
 * their required dependencies from the service layer.
 */
class ControllerServices
{
    /**
     * Constructor.
     *
     * @param ModelServices $models Service provider for model layer dependencies.
     */
    public function __construct(private ModelServices $models)
    {
    }

    /**
     * Creates and returns the HomeController instance.
     *
     * @return HomeController
     */
    public function getHomeController(): HomeController
    {
        return new HomeController($this->models->getUserModel());
    }

    /**
     * Creates and returns the ErrorController instance.
     *
     * @return ErrorController
     */
    public function getErrorController(): ErrorController
    {
        return new ErrorController();
    }
}
