<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Service\Security\Contract\LogoutServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;

final class LogoutService implements LogoutServiceInterface
{
    public function __construct(
        private SessionInterface $session,
        private RememberMeServiceInterface $rememberMeService,
    ) {
    }

    public function logout(): void
    {
        $user = $this->session->get('user');

        $userId = (is_array($user) && isset($user['id']) && is_int($user['id']))
            ? $user['id']
            : null;

        if ($userId !== null) {
            $this->rememberMeService->invalidateRememberMeForUser($userId);
        }

        $this->session->clear();
        $this->session->regenerateAndDeleteOld();

        Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_LOGOUT_SUCCESS, [
            'user_id' => $userId,
        ]);
    }
}
