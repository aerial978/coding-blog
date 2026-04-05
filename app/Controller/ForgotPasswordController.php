<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Http\Request;

final class ForgotPasswordController
{
    public function __construct(
        private Request $request,
        private ForgotPasswordGetHandler $getHandler,
        private ForgotPasswordPostHandler $postHandler,
    ) {
    }

    public function forgotPassword(): void
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
