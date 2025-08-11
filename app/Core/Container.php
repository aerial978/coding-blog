<?php

namespace App\Core;

use App\Core\Container\ControllerServices;
use App\Core\Container\ModelServices;
use App\Core\Container\SystemServices;
use App\Http\Request;

/**
 * Main dependency container for the application.
 *
 * Centralizes access to controllers, models, system services, and factories.
 * This container acts as a service locator for various components.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Container
{
    private ?ControllerServices $controllers = null;
    private ?ModelServices $models           = null;
    private ?SystemServices $system          = null;

    private ?ControllerFactoryInterface $controllerFactory = null;

    /**
     * Returns the ControllerServices instance, creating it if necessary.
     */
    public function getControllers(): ControllerServices
    {
        return $this->controllers ??= new ControllerServices($this->getModels());
    }

    /**
     * Returns the ModelServices instance, creating it if necessary.
     */
    public function getModels(): ModelServices
    {
        return $this->models ??= new ModelServices($this->getSystem());
    }

    /**
     * Returns the SystemServices instance, creating it if necessary.
     */
    public function getSystem(): SystemServices
    {
        return $this->system ??= new SystemServices();
    }

    /**
     * Creates and returns a new HTTP Request object.
     */
    public function getRequest(): Request
    {
        return new Request();
    }

    /**
     * Returns the application configuration.
     */
    public function getAppConfig(): AppConfig
    {
        return $this->getSystem()->getAppConfig();
    }

    /**
     * Returns the PDO database connection.
     */
    public function getPdo(): \PDO
    {
        return $this->getSystem()->getPdo();
    }

    /**
     * Returns a SqlHelper instance for database operations.
     */
    public function getSqlHelper(): SqlHelper
    {
        return $this->getSystem()->getSqlHelper();
    }

    /**
     * Returns a PSR-3 logger for the specified channel.
     */
    public function getLogger(string $channel = 'app'): \Psr\Log\LoggerInterface
    {
        return $this->getSystem()->getLogger($channel);
    }

    /**
     * Returns the UserModel instance.
     */
    public function getUserModel(): \App\Model\UserModel
    {
        return $this->getModels()->getUserModel();
    }

    /**
     * Returns the HomeController instance.
     */
    public function getHomeController(): \App\Controller\HomeController
    {
        return $this->getControllers()->getHomeController();
    }

    /**
     * Returns the ErrorController instance.
     */
    public function getErrorController(): \App\Controller\ErrorController
    {
        return $this->getControllers()->getErrorController();
    }

    /**
     * Returns the controller factory.
     */
    public function getControllerFactory(): ControllerFactoryInterface
    {
        return $this->controllerFactory ??= new DefaultControllerFactory($this);
    }
}
