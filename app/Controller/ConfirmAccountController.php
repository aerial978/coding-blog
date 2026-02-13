<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\Auth\ConfirmAccountHandler;

final class ConfirmAccountController
{
    public function __construct(
        private ConfirmAccountHandler $handler,
    ) {
    }

    public function confirmAccount(): void
    {
        $this->handler->handle();
    }
}
