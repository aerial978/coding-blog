<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Handler\Auth\LoginGetHandler;
use App\Handler\Auth\LoginPostHandler;

final class LoginController
{
    public function __construct(
        private Request $request,
        private LoginGetHandler $getHandler,
        private LoginPostHandler $postHandler,
    ) {
    }

    public function login(): void
    {
        if ($this->request->getMethod() === 'GET') {
            $this->getHandler->handle();
            return;
        }

        /** @var array<string,mixed> $form */
        $form = $this->request->request();

        $this->postHandler->handle($form);
    }
}
