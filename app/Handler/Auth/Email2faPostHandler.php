<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\SessionInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\Email2faServiceInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use App\Service\Security\Email2faService;

final class Email2faPostHandler
{
    private const REDIRECT              = '/coding-blog/login/2fa';
    private const LOGIN_REDIRECT        = '/coding-blog/login';
    private const SUCCESS_REDIRECT      = '/coding-blog';
    private const FORM_ID               = 'email_2fa_form';
    private const RATE_LIMIT_KEY_PREFIX = 'login_2fa_verify';

    public function __construct(
        private readonly Email2faServiceInterface $email2faService,
        private readonly Email2faPendingSessionInterface $email2faSession,
        private readonly SessionInterface $session,
        private readonly FlashInterface $flash,
        private readonly ResponderInterface $responder,
        private readonly CsrfTokenInterface $csrf,
        private readonly HoneypotGuardInterface $honeypotGuard,
        private readonly SubmissionDelayGuardInterface $submissionDelayGuard,
        private readonly RateLimitGuardInterface $rateLimitGuard,
        private readonly RememberMeServiceInterface $rememberMeService,
        private readonly RememberMeCookieManagerInterface $rememberMeManager,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        $userId = $this->resolvePendingUserId();

        if ($userId === null) {
            return;
        }

        $context = $this->makeContext($userId);

        if (!$this->assertValidCsrf($form)) {
            return;
        }

        if (!$this->assertSecurityGuards($form, $context, $userId)) {
            return;
        }

        $code   = $this->strOrEmpty($form['code'] ?? null);
        $result = $this->email2faService->verifyCode($userId, $code);

        $this->handleVerificationResult($result, $userId);
    }

    /**
     * @param array<string, mixed> $form
     */
    private function assertValidCsrf(array $form): bool
    {
        $csrfToken = $this->strOrEmpty($form['csrf_token'] ?? null);

        if ($this->csrf->validateToken(self::FORM_ID, $csrfToken)) {
            return true;
        }

        Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_RETRY, [
            'reason' => 'email_2fa_invalid_csrf',
        ]);

        $this->flash->add(
            'error',
            'Requête invalide. Veuillez réessayer.'
        );

        $this->responder->redirect(self::REDIRECT);

        return false;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $context
     */
    private function assertSecurityGuards(array $form, array $context, int $userId): bool
    {
        if (
            !$this->honeypotGuard->assertClean([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
            ])
        ) {
            return false;
        }

        if (
            !$this->submissionDelayGuard->assertPassed([
            'form_id'  => self::FORM_ID,
            'redirect' => self::REDIRECT,
            'context'  => $context,
            'policy'   => [
                'max_delay_exceeded' => [
                    'flash' => 'error',
                    'code'  => ErrorCode::AUTH_FORM_EXPIRED,
                ],
            ],
            'default' => [
                'flash' => 'error',
                'code'  => ErrorCode::AUTH_RETRY,
            ],
            ])
        ) {
            return false;
        }

        return $this->rateLimitGuard->assertAllowed([
            'key'           => self::RATE_LIMIT_KEY_PREFIX . ':' . $userId,
            'limit'         => 5,
            'window_sec'    => 300,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/login/2fa',
            'flash_type'    => 'error',
            'put_old'       => null,
            'log_ctx'       => $context,
        ]);
    }

    private function completeLogin(int $userId): void
    {
        $rememberMeRequested = $this->email2faSession->wasRememberMeRequested();

        $this->session->regenerateAndDeleteOld();

        $this->session->set('user', [
            'id'    => $userId,
            'roles' => ['USER'],
        ]);

        if ($rememberMeRequested) {
            $this->createRememberMeCookie($userId);
        }

        $this->email2faSession->clear();

        Logger::logCodeAndGetMessage('auth', 'info', 'email_2fa_success', [
            'user_id' => $userId,
        ]);

        $this->flash->add('success', 'Connexion réussie.');
        $this->responder->redirect(self::SUCCESS_REDIRECT);
    }

    private function createRememberMeCookie(int $userId): void
    {
        $token = $this->rememberMeService->createRememberMeToken($userId);

        if ($token === null) {
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'reason'  => 'remember_me_token_creation_failed_after_2fa',
                'user_id' => $userId,
            ]);

            return;
        }

        $this->rememberMeManager->createCookie($token);
    }

    private function resolvePendingUserId(): ?int
    {
        if (!$this->email2faSession->hasPending() || $this->email2faSession->isExpired()) {
            $this->email2faSession->clear();

            $this->flash->add(
                'error',
                'Votre session de vérification a expiré. Veuillez vous reconnecter.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);

            return null;
        }

        $userId = $this->email2faSession->getPendingUserId();

        if ($userId !== null && $userId > 0) {
            return $userId;
        }

        $this->email2faSession->clear();

        $this->flash->add(
            'error',
            'Votre session de vérification est invalide. Veuillez vous reconnecter.'
        );

        $this->responder->redirect(self::LOGIN_REDIRECT);

        return null;
    }

    private function handleVerificationResult(string $result, int $userId): void
    {
        if ($result === Email2faService::VERIFY_SUCCESS) {
            $this->completeLogin($userId);
            return;
        }

        if ($result === Email2faService::VERIFY_TOO_MANY_ATTEMPTS) {
            $this->email2faSession->clear();

            $this->flash->add(
                'error',
                'Trop de tentatives incorrectes. Veuillez recommencer la connexion.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);
            return;
        }

        $this->flash->add(
            'error',
            'Le code de vérification est invalide ou expiré.'
        );

        $this->responder->redirect(self::REDIRECT);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContext(int $userId): array
    {
        return [
            'form'    => self::FORM_ID,
            'user_id' => $userId,
        ];
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
