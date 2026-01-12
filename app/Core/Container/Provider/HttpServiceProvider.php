<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Responder;
use Psr\Container\ContainerInterface;

final class HttpServiceProvider
{
    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    public static function getDefinitions(): array
    {
        return [
            ResponderInterface::class => static function (ContainerInterface $container): ResponderInterface {
                /** @var View $view */
                $view = $container->get(View::class);

                return new Responder($view);
            },
        ];
    }
}
