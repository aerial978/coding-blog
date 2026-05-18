<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Handler\Auth\Email2faGetHandler;
use App\Handler\Auth\Email2faPostHandler;
use App\Handler\Auth\Email2faResendPostHandler;

final class Email2faController
{
    public function __construct(
        private readonly Request $request,
        private readonly Email2faGetHandler $getHandler,
        private readonly Email2faPostHandler $postHandler,
        private readonly Email2faResendPostHandler $resendPostHandler,
    ) {
    }

    public function index(): void
    {
        $this->getHandler->handle();
    }

    /**
     * @param array<string, mixed> $form
     */
    public function verify(): void
    {
        $this->postHandler->handle($this->request->request());
    }

    /**
     * @param array<string, mixed> $form
     */
    public function resend(): void
    {
        $this->resendPostHandler->handle($this->request->request());
    }
}