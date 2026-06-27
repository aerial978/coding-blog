<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;

final class RememberMeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthCheckerInterface $authChecker,
        private RememberMeCookieManagerInterface $cookieManager,
        private RememberMeServiceInterface $rememberMeService,
    ) {
    }

    public function handle(Request $request, string $uri, string $method): bool
    {
        if ($this->authChecker->isAuthenticated()) {
            return true;
        }

        $token = $this->cookieManager->getCookieValue();
        if ($token === null) {
            return true;
        }

        $restored = $this->rememberMeService->restoreSessionFromToken($token);
        if (!$restored) {
            $this->cookieManager->expireCookie();
        }

        return true;
    }
}
