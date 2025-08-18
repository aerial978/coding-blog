<?php

declare(strict_types=1);

namespace App\Core\Container\Provider;

use Psr\Container\ContainerInterface;
use App\Core\View;
use App\Core\Database;
use App\Core\SqlHelper;
use App\Core\Logger;
use App\Http\Request;

final class SystemServiceProvider
{
    public static function getDefinitions(): array
    {
        return [
            \PDO::class         => fn () => (new Database())->getConnection(),
            SqlHelper::class   => fn (ContainerInterface $c) => new SqlHelper($c->get(\PDO::class)),

            View::class        => fn () => new View(),
            Request::class           => fn () => new Request(),

            'logger.app'       => fn () => Logger::getLogger('app'),
            'logger.error'     => fn () => Logger::getLogger('error'),
        ];
    }
}