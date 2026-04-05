<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\Provider\HttpServiceProvider;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Responder;
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

    public function testDefinitionsContainResponderInterface(): void
    {
        $definitions = HttpServiceProvider::getDefinitions();

        $this->assertArrayHasKey(ResponderInterface::class, $definitions);
    }

    public function testResponderInterfaceDefinitionBuildsResponder(): void
    {
        $definitions = HttpServiceProvider::getDefinitions();

        $view = $this->createMock(View::class);

        $container = $this->makeContainer([
            View::class => $view,
        ]);

        $responder = $definitions[ResponderInterface::class]($container);

        $this->assertInstanceOf(Responder::class, $responder);
        $this->assertInstanceOf(ResponderInterface::class, $responder);
    }
}
