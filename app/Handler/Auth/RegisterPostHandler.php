<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;

final class RegisterPostHandler
{
    private const REDIRECT        = '/coding-blog/register';
    private const FORM_ID         = 'register';
    private const TURNSTILE_FIELD = 'cf-turnstile-response';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuardInterface $honeypotGuard,
        private SubmissionDelayGuardInterface $submissionDelayGuard,
        private RateLimitGuardInterface $rateLimitGuard,
        private TurnstileGuardInterface $turnstileGuard,
        private ErrorListNormalizer $errorListNormalizer,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        [$email, $username] = $this->extractInput($form);
        $context            = $this->makeContext($email, $username);

        if (!$this->passesHoneypot($form, $context)) {
            return;
        }

        if (!$this->passesSubmissionDelay($context)) {
            return;
        }

        if (!$this->assertTurnstile($form, $context)) {
            return;
        }

        if (!$this->passesRateLimit($email, $username)) {
            return;
        }

        try {
            $result = $this->securityService->register($form);
        } catch (\Throwable $e) {
            $this->replyTechnicalError($e, $email, $username);
            return;
        }

        $this->handleRegisterOutcome($result, $email, $username);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $context
     */
    private function passesHoneypot(array $form, array $context): bool
    {
        return $this->honeypotGuard->assertClean([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
        ]);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function passesSubmissionDelay(array $context): bool
    {
        return $this->submissionDelayGuard->assertPassed([
            'form_id'   => self::FORM_ID,
            'redirect'  => self::REDIRECT,
            'context'   => $context,
            'policy'    => [
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'   => ['flash' => 'error', 'code' => ErrorCode::AUTH_TECHNICAL_ERROR],
        ]);
    }

    private function passesRateLimit(string $email, string $username): bool
    {
        $allowed = $this->rateLimitGuard->assertAllowed([
            'key'           => 'registration',
            'limit'         => 5,
            'window_sec'    => 300,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/register',
            'flash_type'    => 'error',
            'put_old'       => ['username' => $username, 'email' => $email],
            'log_ctx'       => [
                'email'    => $email    !== '' ? $email : null,
                'username' => $username !== '' ? $username : null,
            ],
        ]);

        if ($allowed) {
            return true;
        }

        $this->flash->add('info', MessageManager::get(ErrorCode::AUTH_PASSWORD_REENTER));
        return false;
    }

    /**
     * @param array<string,mixed> $form
     * @return array{0:string,1:string}
     */
    private function extractInput(array $form): array
    {
        $email    = $this->strOrEmpty($form['email'] ?? null);
        $username = $this->strOrEmpty($form['username'] ?? null);

        return [$email, $username];
    }

    /**
    * @return array<string,mixed>
    */
    private function makeContext(string $email, string $username): array
    {
        return [
            'form'     => self::FORM_ID,
            'email'    => $email    !== '' ? $email : null,
            'username' => $username !== '' ? $username : null,
        ];
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $context
     */
    private function assertTurnstile(array $form, array $context): bool
    {
        Logger::getLogger('app')->info('turnstile_token_debug', [
            'has_field' => array_key_exists(self::TURNSTILE_FIELD, $form),
            'len'       => is_string($form[self::TURNSTILE_FIELD] ?? null) ? strlen($form[self::TURNSTILE_FIELD]) : null,
            'preview'   => is_string($form[self::TURNSTILE_FIELD] ?? null) ? substr($form[self::TURNSTILE_FIELD], 0, 12) : null,
        ]);

        return $this->turnstileGuard->assertValid([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'context'     => $context,
            'token_field' => self::TURNSTILE_FIELD,
            'debug_log'   => true,
        ]);
    }

    private function replyTechnicalError(\Throwable $exception, string $email, string $username): void
    {
        $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', ErrorCode::AUTH_TECHNICAL_ERROR, [
            'exception' => $exception->getMessage(),
            'email'     => $email ?: null,
            'username'  => $username ?: null,
        ]));
        $this->flash->put('old', ['username' => $username, 'email' => $email]);
        $this->responder->redirect(self::REDIRECT);
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
        $this->responder->redirect(self::REDIRECT);
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
        $this->responder->redirect(self::REDIRECT);
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

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
