<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\Auth\ResetPasswordGetHandler;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Request;

final class ResetPasswordController
{
    public function __construct(
        private Request $request,
        private ResetPasswordGetHandler $getHandler,
        private ResetPasswordPostHandler $postHandler,
    ) {
    }

    public function resetPassword(): void
    {
        if ($this->request->getMethod() === 'GET') {
            $token = $this->getQueryToken();

            $this->getHandler->handle($token);
            return;
        }

        /** @var array<string, mixed> $form */
        $form = $this->request->request();

        $this->postHandler->handle($form);
    }

    private function getQueryToken(): string
    {
        /** @var array<string, mixed> $query */
        $query = $this->request->query();

        $token = $query['token'] ?? null;

        return is_string($token) ? trim($token) : '';
    }
}
