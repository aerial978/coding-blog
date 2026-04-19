<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Service\Security\Contract\LogoutServiceInterface;

final class LogoutService implements LogoutServiceInterface
{
    public function __construct(
        private SessionInterface $session,
    ) {
    }

    public function logout(): void
    {
        $userId = $this->session->get('user_id');

        $this->session->clear();
        $this->session->regenerateAndDeleteOld();

        Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_LOGOUT_SUCCESS, [
            'user_id' => is_int($userId) || is_string($userId) ? $userId : null,
        ]);
    }
}
