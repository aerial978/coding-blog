<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Http\Contract\ResponderInterface;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\RateLimitGuard;

final class ResendConfirmationPostHandler
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuard $honeypotGuard,
        private SubmissionDelayGuard $submissionDelayGuard,
        private RateLimitGuard $rateLimitGuard,
        // étape suivante : injecter HoneypotGuard, SubmissionDelayGuard, RateLimitGuard…
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        $email = $this->strOrEmpty($form['email'] ?? null);

        $contextBase = ['email' => $email ?: null];

        // Honeypot (politique resend = succès silencieux)
        if (!$this->honeypotGuard->assertClean([
            'form'       => $form,
            'redirect'   => '/coding-blog/resend-confirmation',
            'flash_type' => 'success',
            'code'       => ErrorCode::AUTH_RESEND_EMAIL_SENT,
            'log_level'  => 'warning',
            'log_channel'=> 'auth',
            'context'    => [
                'email' => $email ?: null,
                // 'reason' n'est pas nécessaire ici : le guard l’ajoute déjà ('honeypot')
            ],
        ])) {
            return;
        }

        if (!$this->submissionDelayGuard->assertPassed([
            'form_id'   => 'resend_confirm',
            'redirect'  => '/coding-blog/resend-confirmation',
            'context'   => $contextBase,
            'policy'    => [
                'max_delay_exceeded' => ['flash' => 'error',   'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'   => ['flash' => 'success', 'code' => ErrorCode::AUTH_RESEND_EMAIL_SENT],
        ])) {
            return;
        }

        if (!$this->rateLimitGuard->assertAllowed([
            'key'        => 'resend_confirm',
            'limit'      => 5,
            'window_sec' => 300,
            'redirect'   => '/coding-blog/resend-confirmation',
            'route_for_log' => '/resend-confirmation',
            'log_ctx'    => [
                'email'  => $email ?: null,
                'reason' => 'rate_limited', // utile pour vos logs, optionnel
            ],

            // Opt-in : anti-énumération => succès silencieux
            'silent_success'            => true,
            'silent_success_flash_type' => 'success',
            'silent_success_code'       => ErrorCode::AUTH_RESEND_EMAIL_SENT,
        ])) {
            return;
        }

        $result = $this->callResendConfirmationSafely($email);
        if ($result === null) {
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->handleResendOutcome($result, $email);
    }

    // -------------------------
    // Helpers (à extraire ensuite)
    // -------------------------

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function callResendConfirmationSafely(string $email): ?array
    {
        try {
            return $this->securityService->resendConfirmation($email);
        } catch (\Throwable $e) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
                'exception' => $e->getMessage(),
                'email'     => $email,
            ]));
            return null;
        }
    }

    /**
     * @param array<string,mixed> $result
     */
    private function handleResendOutcome(array $result, string $email): void
    {
        $code = $this->stringify($result['error'] ?? null);

        if ($code === (string) ErrorCode::AUTH_ALREADY_CONFIRMED) {
            $this->flash->add('info', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ALREADY_CONFIRMED));
            $this->responder->redirect('/coding-blog');
            return;
        }

        if ($code !== '') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', $code, ['email' => $email]));
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return;
        }

        $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email' => $email,
        ]));
        $this->responder->redirect('/coding-blog/resend-confirmation');
    }

    private function stringify(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

}
