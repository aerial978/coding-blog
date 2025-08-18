<?php

namespace Tests\Unit\Core;

use App\Controller\ErrorController;
use App\Controller\HomeController;
use App\Core\Container;
use App\Core\DefaultControllerFactory;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DummyController;

/**
 * Unit tests for the DefaultControllerFactory class.
 *
 * This test suite verifies that the DefaultControllerFactory correctly
 * creates controller instances using the Container for known controllers
 * and directly instantiates unknown controllers.
 */
final class DefaultControllerFactoryTest extends TestCase
{
    /**
     * Test that the factory returns the HomeController instance from the container.
     *
     * This ensures that when requesting a HomeController, the factory
     * calls the container's getHomeController() method.
     */
    public function testCreateReturnsHomeControllerFromContainer(): void
    {
        $userModelMock = $this->createMock(\App\Model\UserModel::class);

        $homeController = new HomeController($userModelMock);

        $container = $this->createMock(Container::class);
        $container->expects($this->once())
            ->method('getHomeController')
            ->willReturn($homeController);

        $factory    = new DefaultControllerFactory($container);
        $controller = $factory->create(HomeController::class);

        $this->assertInstanceOf(HomeController::class, $controller);
    }

    /**
     * Test that the factory returns the ErrorController instance from the container.
     *
     * This ensures that when requesting an ErrorController, the factory
     * calls the container's getErrorController() method.
     */
    public function testCreateReturnsErrorControllerFromContainer(): void
    {
        $container = $this->createMock(Container::class);
        $container->expects($this->once())
            ->method('getErrorController')
            ->willReturn(new ErrorController());

        $factory    = new DefaultControllerFactory($container);
        $controller = $factory->create(ErrorController::class);

        $this->assertInstanceOf(ErrorController::class, $controller);
    }

    /**
     * Test that the factory directly instantiates an unknown controller class.
     *
     * This ensures that if the requested controller is not known to the factory,
     * it will be instantiated directly without using the container.
     */
    public function testCreateInstantiatesUnknownController(): void
    {
        $container = $this->createMock(Container::class);

        require_once __DIR__ . '/../../Fixtures/DummyController.php';

        $factory    = new DefaultControllerFactory($container);
        $controller = $factory->create(DummyController::class);

        $this->assertInstanceOf(DummyController::class, $controller);
    }
}
