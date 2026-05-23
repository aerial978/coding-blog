<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Http\Responder;
use App\Http\ViewContextProvider;
use Psr\Container\ContainerInterface;

final class HttpServiceProvider
{
    /**
     * @return array<class-string, \Closure(ContainerInterface):object>
     */
    public static function getDefinitions(): array
    {
        return [
            ViewContextProvider::class => static function (ContainerInterface $container): ViewContextProvider {
                /** @var FlashInterface $flash */
                $flash = $container->get(FlashInterface::class);

                /** @var SessionInterface $session */
                $session = $container->get(SessionInterface::class);

                return new ViewContextProvider($flash, $session);
            },

            Responder::class => static function (ContainerInterface $container): Responder {
                /** @var View $view */
                $view = $container->get(View::class);

                /** @var ViewContextProvider $contextProvider */
                $contextProvider = $container->get(ViewContextProvider::class);

                return new Responder($view, $contextProvider);
            },

            ResponderInterface::class => static function (ContainerInterface $container): ResponderInterface {
                /** @var View $view */
                $view = $container->get(View::class);

                /** @var ViewContextProvider $contextProvider */
                $contextProvider = $container->get(ViewContextProvider::class);

                return new Responder($view, $contextProvider);
            },
        ];
    }
}
