<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\Email2faServiceInterface;

final class Email2faResendPostHandler
{
    private const REDIRECT = '/coding-blog/login/2fa';
    private const LOGIN_REDIRECT = '/coding-blog/login';
    private const FORM_ID = 'email_2fa_resend_form';
    private const RATE_LIMIT_KEY_PREFIX = 'login_2fa_resend';

    public function __construct(
        private readonly Email2faServiceInterface $email2faService,
        private readonly Email2faPendingSessionInterface $email2faPendingSession,
        private readonly UserModelInterface $userModel,
        private readonly FlashInterface $flash,
        private readonly ResponderInterface $responder,
        private readonly CsrfTokenInterface $csrf,
        private readonly HoneypotGuardInterface $honeypotGuard,
        private readonly SubmissionDelayGuardInterface $submissionDelayGuard,
        private readonly RateLimitGuardInterface $rateLimitGuard,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        if (!$this->email2faPendingSession->hasPending() || $this->email2faPendingSession->isExpired()) {
            $this->email2faPendingSession->clear();

            $this->flash->add(
                'error',
                'Votre session de vérification a expiré. Veuillez vous reconnecter.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);
            return;
        }

        $userId = $this->email2faPendingSession->getPendingUserId();

        if ($userId === null || $userId <= 0) {
            $this->email2faPendingSession->clear();

            $this->flash->add(
                'error',
                'Votre session de vérification est invalide. Veuillez vous reconnecter.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);
            return;
        }

        $context = $this->makeContext($userId);

        if (!$this->assertValidCsrf($form)) {
            return;
        }

        if (!$this->assertSecurityGuards($form, $context, $userId)) {
            return;
        }

        $user = $this->userModel->findOneById($userId);

        if ($user === null || !$user->isEmail2faEnabled()) {
            $this->email2faPendingSession->clear();

            $this->flash->add(
                'error',
                'Votre session de vérification est invalide. Veuillez vous reconnecter.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);
            return;
        }

        if (!$this->email2faService->resendCode($user)) {
            $this->flash->add(
                'error',
                'Impossible de renvoyer le code de vérification. Veuillez réessayer.'
            );

            $this->responder->redirect(self::REDIRECT);
            return;
        }

        $this->flash->add(
            'info',
            'Un nouveau code de vérification vous a été envoyé par e-mail.'
        );

        $this->responder->redirect(self::REDIRECT);
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
        if (!$this->honeypotGuard->assertClean([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
        ])) {
            return false;
        }

        if (!$this->submissionDelayGuard->assertPassed([
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
        ])) {
            return false;
        }

        return $this->rateLimitGuard->assertAllowed([
            'key'           => self::RATE_LIMIT_KEY_PREFIX . ':' . $userId,
            'limit'         => 3,
            'window_sec'    => 300,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/login/2fa/resend',
            'flash_type'    => 'error',
            'put_old'       => null,
            'log_ctx'       => $context,
        ]);
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