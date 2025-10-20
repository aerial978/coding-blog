<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use App\Core\Container\AppContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class AppContainerTest extends TestCase
{
    #[Test]
    public function set_and_get_with_direct_object_returns_same_instance(): void
    {
        $obj = new \stdClass();
        $c   = new AppContainer();
        $c->set('foo', $obj);
        $this->assertSame($obj, $c->get('foo'));
        $this->assertTrue($c->has('foo'));
    }

    #[Test]
    public function factory_is_called_once_and_result_is_cached(): void
    {
        $calls = 0;
        $c     = new AppContainer([
            'svc' => function (AppContainer $container) use (&$calls) {

                $calls++;
                return new \stdClass();
            },
        ]);
        $a = $c->get('svc');
        $b = $c->get('svc');
        $this->assertSame($a, $b, 'Doit renvoyer le même singleton mis en cache.');
        $this->assertSame(1, $calls, 'La factory ne doit être appelée qu’une seule fois.');
    }

    #[Test]
    public function has_returns_false_then_true_after_set(): void
    {
        $c = new AppContainer();
        $this->assertFalse($c->has('x'));
        $c->set('x', new \stdClass());
        $this->assertTrue($c->has('x'));
    }

    #[Test]
    public function get_throws_psr_not_found_when_missing(): void
    {
        $c = new AppContainer();
        $this->expectException(NotFoundExceptionInterface::class);
        $c->get('missing');
    }

    #[Test]
    public function factory_returning_null_is_wrapped_as_container_exception(): void
    {
        $c = new AppContainer([
            'nil' => function () {
                return null;
            }, // retourne null → doit être refusé
        ]);
        $this->expectException(ContainerExceptionInterface::class);
        $c->get('nil');
    }

    #[Test]
    public function factory_throwing_error_is_wrapped_as_container_exception(): void
    {
        $c = new AppContainer([
            'boom' => function () {

                throw new \RuntimeException('boom');
            },
        ]);
        $this->expectException(ContainerExceptionInterface::class);
        $c->get('boom');
    }

    #[Test]
    public function set_can_replace_existing_entry(): void
    {
        $c     = new AppContainer();
        $first = new \stdClass();
        $c->set('rep', $first);
        $this->assertSame($first, $c->get('rep'));
        // Remplace par une factory ; après résolution on obtient une autre instance
        $c->set('rep', fn () => new \stdClass());
        $second = $c->get('rep');
        $this->assertNotSame($first, $second);
        $this->assertSame($second, $c->get('rep')); // mémorisation
    }
}
