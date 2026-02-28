<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;

final class ForgotPasswordController
{
    public function __construct(
        private Request $request,
        private ForgotPasswordGetHandler $getHandler,
        private ForgotPasswordPostHandler $postHandler,
    ) {
    }

    public function ForgotPassword(): void
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