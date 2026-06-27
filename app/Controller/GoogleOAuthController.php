<?php

declare(strict_types=1);

namespace App\Controller;

use App\Handler\OAuth\GoogleOAuthCallbackHandler;
use App\Handler\OAuth\GoogleOAuthStartHandler;
use App\Http\Request;

final class GoogleOAuthController
{
    public function __construct(
        private GoogleOAuthStartHandler $startHandler,
        private GoogleOAuthCallbackHandler $callbackHandler,
        private Request $request,
    ) {
    }

    public function redirect(): void
    {
        $this->startHandler->handle();
    }

    public function callback(): void
    {
        /** @var array<string,mixed> $query */
        $query = $this->request->query();

        $this->callbackHandler->handle($query);
    }
}
