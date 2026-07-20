<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\SecurityServiceInterface;

final class LogoutHandler
{
    private const LOGIN_REDIRECT = '/coding-blog/login';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private RememberMeCookieManagerInterface $rememberMeManager,
    ) {
    }

    public function handle(): void
    {
        $this->securityService->logout();

        $this->rememberMeManager->expireCookie();

        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::AUTH_LOGOUT_SUCCESS)
        );

        $this->responder->redirect(self::LOGIN_REDIRECT);
    }
}
