<?php

declare(strict_types=1);

namespace App\Handler\Account;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Service\Account\Contract\AccountPasswordChangeServiceInterface;

final class AccountPasswordChangeHandler
{
    private const REDIRECT                  = '/account';
    private const RATE_LIMIT_KEY            = 'account_password_change';
    private const RATE_LIMIT_MAX_ATTEMPTS   = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private AccountPasswordChangeServiceInterface $passwordService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private RememberMeCookieManagerInterface $cookieManager,
        private SessionInterface $session,
        private RateLimitGuardInterface $rateLimitGuard,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        if (
            !$this->rateLimitGuard->assertAllowed([
            'key'           => self::RATE_LIMIT_KEY,
            'limit'         => self::RATE_LIMIT_MAX_ATTEMPTS,
            'window_sec'    => self::RATE_LIMIT_WINDOW_SECONDS,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/account/password',
            'flash_type'    => 'error',
            ])
        ) {
            return;
        }

        $result = $this->passwordService->changePassword($form);

        if (isset($result['errors'])) {
            foreach ($result['errors'] as $errorCode) {
                $this->flash->add(
                    'error',
                    MessageManager::get($errorCode)
                );
            }

            $this->responder->redirect(self::REDIRECT);
            return;
        }

        $this->cookieManager->expireCookie();
        $this->session->regenerateAndDeleteOld();

        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::ACCOUNT_PASSWORD_UPDATE_SUCCESS)
        );

        $this->responder->redirect(self::REDIRECT);
    }
}
