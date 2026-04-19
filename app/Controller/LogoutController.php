<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\Auth\LogoutHandler;

final class LogoutController
{
    public function __construct(
        private LogoutHandler $handler,
    ) {
    }

    public function logout(): void
    {
        $this->handler->handle();
    }
}
