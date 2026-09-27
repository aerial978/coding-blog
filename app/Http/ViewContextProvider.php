<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\FormId;
use App\Security\Contract\CsrfTokenInterface;

final class ViewContextProvider
{
    private const AUTH_SESSION_KEY      = 'user';
    private const EMAIL_2FA_PENDING_KEY = 'auth_2fa_pending';

    public function __construct(
        private readonly FlashInterface $flash,
        private readonly SessionInterface $session,
        private readonly CsrfTokenInterface $csrf,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        $user = $this->getAuthenticatedUser();

        return [
            'flashes'             => $this->flash->consumeMany(),
            'auth_user'           => $user,
            'is_authenticated'    => $user !== null,
            'email_2fa_pending'   => $this->session->has(self::EMAIL_2FA_PENDING_KEY),
            'show_header'         => $user !== null,
            'logout_csrf_token'   => $user !== null
                ? $this->csrf->generateToken(FormId::LOGOUT)
                : '',
            'turnstile_site_key'  => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAuthenticatedUser(): ?array
    {
        $user = $this->session->get(self::AUTH_SESSION_KEY);

        if (!is_array($user)) {
            return null;
        }

        /** @var array<string, mixed> $user */
        return $user;
    }
}
