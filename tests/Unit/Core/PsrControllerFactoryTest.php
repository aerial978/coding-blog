<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\PsrControllerFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class PsrControllerFactoryTest extends TestCase
{
    #[Test]
    public function create_returns_instance_from_container(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $expected  = new \stdClass();
        $container->expects($this->once())
            ->method('get')
            ->with(\stdClass::class)
            ->willReturn($expected);
        $factory = new PsrControllerFactory($container);
        $obj     = $factory->create(\stdClass::class);
        $this->assertSame($expected, $obj);
    }

    #[Test]
    public function create_bubbles_not_found_exception(): void
    {
        $ex = new class ('not found') extends RuntimeException implements NotFoundExceptionInterface {
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException($ex);
        $this->expectException($ex::class);
        $factory = new PsrControllerFactory($container);
        $factory->create('Missing\\Controller');
    }

    #[Test]
    public function create_bubbles_container_exception(): void
    {
        $ex = new class ('container error') extends RuntimeException implements ContainerExceptionInterface {
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException($ex);
        $this->expectException($ex::class);
        $factory = new PsrControllerFactory($container);
        $factory->create('Any\\Service\\Id');
    }
}
