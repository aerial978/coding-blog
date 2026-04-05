<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Http\Request;

final class ResendConfirmationController
{
    public function __construct(
        private Request $request,
        private ResendConfirmationGetHandler $getHandler,
        private ResendConfirmationPostHandler $postHandler,
    ) {
    }

    public function resendConfirmation(): void
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
