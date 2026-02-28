<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\TurnstileGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Support\ErrorListNormalizer;

final class RegisterPostHandler
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuard $honeypotGuard,
        private SubmissionDelayGuard $submissionDelayGuard,
        private RateLimitGuard $rateLimitGuard,
        private TurnstileGuard $turnstileGuard,
        private ErrorListNormalizer $errorListNormalizer,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        $email    = $this->strOrEmpty($form['email'] ?? null);
        $username = $this->strOrEmpty($form['username'] ?? null);

        $contextBase = $this->buildRegisterContextBase($email, $username);

        // Honeypot (extrait vers HoneypotGuard) : register = erreur + redirect
        if (!$this->honeypotGuard->assertClean([
            'form'       => $form,
            'redirect'   => '/coding-blog/register',
            'flash_type' => 'error',
            'code'       => ErrorCode::AUTH_RETRY,
            'log_level'  => 'warning',
            'log_channel'=> 'auth',
            'context'    => $contextBase,
        ])) {
            return;
        }

        // SubmissionDelay (extrait vers SubmissionDelayGuard)
        if (!$this->submissionDelayGuard->assertPassed([
            'form_id'   => 'register',
            'redirect'  => '/coding-blog/register',
            'context'   => $contextBase,
            'policy'    => [
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'   => ['flash' => 'error', 'code' => ErrorCode::AUTH_TECHNICAL_ERROR],
        ])) {
            return;
        }

        Logger::getLogger('app')->info('turnstile_token_debug', [
            'has_field' => array_key_exists('cf-turnstile-response', $form),
            'len'       => is_string($form['cf-turnstile-response'] ?? null) ? strlen($form['cf-turnstile-response']) : null,
            'preview'   => is_string($form['cf-turnstile-response'] ?? null) ? substr($form['cf-turnstile-response'], 0, 12) : null,
        ]);

        // Turnstile (extrait vers TurnstileGuard)
        if (!$this->turnstileGuard->assertValid([
            'form'       => $form,
            'redirect'   => '/coding-blog/register',
            'context'    => $contextBase,
            'token_field'=> 'cf-turnstile-response',
            'debug_log'  => true, // garde votre log turnstile_token_debug
            // 'ip' => null, // optionnel : sinon auto via REMOTE_ADDR
        ])) {
            return;
        }

        // Rate limit registration (prioritaire à CSRF/service, après filtres anti-bot)
        if (!$this->rateLimitGuard->assertAllowed([
            'key'        => 'registration',
            'limit'      => 5,
            'window_sec' => 300,
            'redirect'   => '/coding-blog/register',
            'route_for_log' => '/register',
            'flash_type' => 'error',
            'put_old'    => ['username' => $username, 'email' => $email],
            'log_ctx'    => [
                'email'    => $email ?: null,
                'username' => $username ?: null,
            ],
        ])) {
            // Vous gardez ce message spécifique au register (si vous le souhaitez)
            $this->flash->add('info', MessageManager::get(ErrorCode::AUTH_PASSWORD_REENTER));
            return;
        }

        try {
            $result = $this->securityService->register($form);
        } catch (\Throwable $e) {
            $this->handleRegisterTechnicalError($e, $email, $username);
            return;
        }

        $this->handleRegisterOutcome($result, $email, $username);
    }

    // =========================
    // ------- HELPERS ---------
    // =========================

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildRegisterContextBase(string $email, string $username): array
    {
        return [
            'email'    => $email ?: null,
            'username' => $username ?: null,
        ];
    }

    private function handleRegisterTechnicalError(\Throwable $exception, string $email, string $username): void
    {
        $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'exception' => $exception->getMessage(),
            'email'     => $email ?: null,
            'username'  => $username ?: null,
        ]));
        $this->flash->put('old', ['username' => $username, 'email' => $email]);
        $this->responder->redirect('/coding-blog/register');
    }

    /**
     * @param array<string,mixed> $result
     */
    private function handleRegisterOutcome(array $result, string $email, string $username): void
    {
        $errors = $this->errorListNormalizer->normalize($result['errors'] ?? null);

        if ($this->handleRegisterErrorsIfAny($errors, $result, $email, $username)) {
            return;
        }

        $this->handleRegisterSuccessFlag($result, $email, $username);
        $this->responder->redirect('/coding-blog/register');
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $result
     */
    private function handleRegisterErrorsIfAny(array $errors, array $result, string $email, string $username): bool
    {
        if ($errors === []) {
            return false;
        }

        if (in_array((string) ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, $errors, true)) {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_CONFIRM_EMAIL_SEND_FAILED, [
                'email'     => $email,
                'username'  => $username,
            ]));
            $this->flash->put('old', ['email' => $email]);
            $this->responder->redirect('/coding-blog/resend-confirmation');
            return true;
        }

        foreach ($errors as $code) {
            $this->flash->add('error', MessageManager::get($code));
        }

        $old = (isset($result['old']) && is_array($result['old']))
            ? $result['old']
            : ['username' => $username, 'email' => $email];

        $this->flash->put('old', $old);
        $this->responder->redirect('/coding-blog/register');
        return true;
    }

    /**
     * @param array<string,mixed> $result
     */
    private function handleRegisterSuccessFlag(array $result, string $email, string $username): void
    {
        if (!empty($result['ok'])) {
            $this->flash->put('register_state', ['email' => $email]);
            // @codeCoverageIgnoreStart
            Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_ACCOUNT_CONFIRMATION_SENT, [
                'email'     => $email,
                'username'  => $username,
            ]);
            // @codeCoverageIgnoreEnd
        }
    }
}
