<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;

final class ResendConfirmationPostHandler
{
    private const REDIRECT       = '/coding-blog/resend-confirmation';
    private const HOME_REDIRECT  = '/coding-blog';
    private const FORM_ID        = 'resend_confirm';
    private const TURNSTILE_FLAG = 'turnstile_resend';
    private const RATE_LIMIT_KEY = 'resend_confirm';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuardInterface $honeypotGuard,
        private SubmissionDelayGuardInterface $submissionDelayGuard,
        private RateLimitGuardInterface $rateLimitGuard,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        Logger::getLogger('auth')->info('resend_post_handler_entry');

        $email   = $this->strOrEmpty($form['email'] ?? null);
        $context = $this->makeContext($email);

        /** @var array{
         *   form: array<string, mixed>,
         *   redirect: string,
         *   flash_type: 'success',
         *   code: string,
         *   log_level: 'warning',
         *   log_channel: string,
         *   context: array<string, mixed>,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $honeypotOptions
         */
        $honeypotOptions = array_merge([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'success',
            'code'        => ErrorCode::AUTH_RESEND_EMAIL_SENT,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
        ], $this->turnstileStepUp());

        if (!$this->honeypotGuard->assertClean($honeypotOptions)) {
            return;
        }

        /** @var array{
         *   form_id: string,
         *   redirect: string,
         *   context: array<string, mixed>,
         *   policy: array<string, array{flash: 'error'|'info'|'success'|'warning', code: string}>,
         *   default: array{flash: 'error'|'info'|'success'|'warning', code: string},
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $delayOptions
         */
        $delayOptions = array_merge([
            'form_id'  => self::FORM_ID,
            'redirect' => self::REDIRECT,
            'context'  => $context,
            'policy'   => [
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'  => ['flash' => 'success', 'code' => ErrorCode::AUTH_RESEND_EMAIL_SENT],
        ], $this->turnstileStepUp());

        if (!$this->submissionDelayGuard->assertPassed($delayOptions)) {
            return;
        }

        /** @var array{
         *   key: string,
         *   limit: int,
         *   window_sec: int,
         *   redirect: string,
         *   route_for_log: string,
         *   log_ctx: array<string, mixed>,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $rateLimitOptions
         */
        $rateLimitOptions = array_merge([
            'key'           => self::RATE_LIMIT_KEY,
            'limit'         => 5,
            'window_sec'    => 300,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/resend-confirmation',
            'log_ctx'       => $this->makeRateLimitContext($email),
        ], $this->turnstileStepUp());

        if (!$this->rateLimitGuard->assertAllowed($rateLimitOptions)) {
            return;
        }

        $result = $this->callResendConfirmationSafely($email);
        if ($result === null) {
            $this->responder->redirect(self::REDIRECT);
            return;
        }

        $this->handleResendOutcome($result, $email);
    }

    /**
     * @return array<string,mixed>
     */
    private function makeContext(string $email): array
    {
        return [
            'form'  => self::FORM_ID,
            'email' => $email !== '' ? $email : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function makeRateLimitContext(string $email): array
    {
        return [
            'email'  => $email !== '' ? $email : null,
            'reason' => 'rate_limited',
        ];
    }

    /**
     * @return array{
     *   flags_bag: string,
     *   set_flags: array<string, mixed>
     * }
     */
    private function turnstileStepUp(): array
    {
        return [
            'flags_bag' => 'security_flags',
            'set_flags' => [self::TURNSTILE_FLAG => true],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function callResendConfirmationSafely(string $email): ?array
    {
        try {
            /** @var array<string, mixed> $result */
            $result = $this->securityService->resendConfirmation($email);

            return $result;
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
            $this->responder->redirect(self::HOME_REDIRECT);
            return;
        }

        if ($code !== '') {
            $this->flash->add('error', Logger::logCodeAndGetMessage('auth', 'error', $code, ['email' => $email]));
            $this->responder->redirect(self::REDIRECT);
            return;
        }

        $this->flash->add('success', Logger::logCodeAndGetMessage('auth', 'info', ErrorCode::AUTH_RESEND_EMAIL_SENT, [
            'email' => $email,
        ]));
        $this->responder->redirect(self::REDIRECT);
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function stringify(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
}
