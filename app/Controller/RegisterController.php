<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Contract\ResponderInterface;
use App\Handler\Auth\RegisterGetHandler;
use App\Handler\Auth\RegisterPostHandler;

final class RegisterController
{
    public function __construct(
        private Request $request,
        private ResponderInterface $responder,
        private RegisterGetHandler $getHandler,
        private RegisterPostHandler $postHandler,
    ) {
    }

    public function register(): void
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
