<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\HttpServiceProvider;
use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Responder;
use App\Http\ViewContextProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class HttpServiceProviderTest extends TestCase
{
    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /**
             * @param array<string, mixed> $services
             */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException('Service not found: ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    public function testDefinitionsContainExpectedKeys(): void
    {
        $definitions = HttpServiceProvider::getDefinitions();

        $this->assertArrayHasKey(ViewContextProvider::class, $definitions);
        $this->assertArrayHasKey(ResponderInterface::class, $definitions);
    }

    public function testViewContextProviderDefinitionBuildsProvider(): void
    {
        $definitions = HttpServiceProvider::getDefinitions();

        $container = $this->makeContainer([
            FlashInterface::class   => $this->createMock(FlashInterface::class),
            SessionInterface::class => $this->createMock(SessionInterface::class),
        ]);

        $provider = $definitions[ViewContextProvider::class]($container);

        $this->assertInstanceOf(ViewContextProvider::class, $provider);
    }

    public function testResponderInterfaceDefinitionBuildsResponder(): void
    {
        $definitions = HttpServiceProvider::getDefinitions();

        $contextProvider = new ViewContextProvider(
            $this->createMock(FlashInterface::class),
            $this->createMock(SessionInterface::class)
        );

        $container = $this->makeContainer([
            View::class                => $this->createMock(View::class),
            ViewContextProvider::class => $contextProvider,
        ]);

        $responder = $definitions[ResponderInterface::class]($container);

        $this->assertInstanceOf(Responder::class, $responder);
        $this->assertInstanceOf(ResponderInterface::class, $responder);
    }
}
