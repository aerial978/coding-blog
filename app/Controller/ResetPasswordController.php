<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Handler\Auth\ResetPasswordGetHandler;

final class ResetPasswordController
{
    public function __construct(
        private Request $request,
        private ResetPasswordGetHandler $getHandler,
        // PostHandler plus tard
    ) {
    }

    public function resetPassword(): void
    {
        if ($this->request->getMethod() === 'GET') {
            $this->getHandler->handle();
            return;
        }

        // POST sera implémenté dans le prochain commit
        header('Location: /coding-blog/reset-password', true, 302);
    }
}