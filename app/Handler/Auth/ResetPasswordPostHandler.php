<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;

final class ResetPasswordPostHandler
{
    private const BASE_REDIRECT  = '/coding-blog/reset-password';
    private const LOGIN_REDIRECT = '/coding-blog/login';
    private const FORM_ID        = 'reset_password';
    private const TURNSTILE_FLAG = 'turnstile_reset';
    private const RATE_LIMIT_KEY = 'reset_password';

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
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        [$token, $password, $confirm] = $this->extractInput($form);
        $redirect                     = $this->makeRedirect($token);
        $context                      = $this->makeContext($token);

        if (!$this->passesHoneypot($form, $context, $redirect)) {
            return;
        }

        if (!$this->passesSubmissionDelay($context, $redirect)) {
            return;
        }

        if (!$this->passesRateLimit($context, $redirect)) {
            return;
        }

        if (!$this->assertTurnstileIfRequired($form, $context, $redirect)) {
            return;
        }

        /** @var array<string, mixed> $result */
        $result = $this->securityService->resetPassword($token, $password, $confirm);

        $this->handleServiceResult($result, $redirect);
    }

    /**
     * @param array<string, mixed> $form
     * @return array{0:string, 1:string, 2:string}
     */
    private function extractInput(array $form): array
    {
        $token    = $this->strOrEmpty($form['token'] ?? null);
        $password = $this->strOrEmpty($form['password'] ?? null);
        $confirm  = $this->extractConfirmPassword($form);

        return [$token, $password, $confirm];
    }

    /**
     * @param array<string, mixed> $form
     */
    private function extractConfirmPassword(array $form): string
    {
        if (array_key_exists('password_confirm', $form)) {
            return $this->strOrEmpty($form['password_confirm']);
        }

        if (array_key_exists('confirm_password', $form)) {
            return $this->strOrEmpty($form['confirm_password']);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $context
     */
    private function passesHoneypot(array $form, array $context, string $redirect): bool
    {
        /** @var array{
         *   form: array<string, mixed>,
         *   redirect: string,
         *   flash_type: 'error',
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
            'redirect'    => $redirect,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
        ], $this->turnstileStepUp());

        return $this->honeypotGuard->assertClean($honeypotOptions);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function passesSubmissionDelay(array $context, string $redirect): bool
    {
        /** @var array{
         *   form_id: string,
         *   redirect: string,
         *   context: array<string, mixed>,
         *   default: array{flash: 'error'|'info'|'success'|'warning', code: string},
         *   min_sec: int,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $delayOptions
         */
        $delayOptions = array_merge([
            'form_id'  => self::FORM_ID,
            'redirect' => $redirect,
            'context'  => $context,
            'default'  => ['flash' => 'error', 'code' => ErrorCode::AUTH_RETRY],
            'min_sec'  => 3,
        ], $this->turnstileStepUp());

        return $this->submissionDelayGuard->assertPassed($delayOptions);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function passesRateLimit(array $context, string $redirect): bool
    {
        /** @var array{
         *   key: string,
         *   limit: int,
         *   window_sec: int,
         *   redirect: string,
         *   route_for_log: string,
         *   flash_type: 'error',
         *   log_ctx: array<string, mixed>,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $rateOptions
         */
        $rateOptions = array_merge([
            'key'           => self::RATE_LIMIT_KEY,
            'limit'         => 5,
            'window_sec'    => 600,
            'redirect'      => $redirect,
            'route_for_log' => '/reset-password',
            'flash_type'    => 'error',
            'log_ctx'       => $context,
        ], $this->turnstileStepUp());

        return $this->rateLimitGuard->assertAllowed($rateOptions);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function handleServiceResult(array $result, string $redirect): void
    {
        if (!empty($result['errors']) && is_array($result['errors'])) {
            /** @var array<string, mixed> $errors */
            $errors = $result['errors'];
            $this->replyValidationErrors($errors, $redirect);
            return;
        }

        if (!empty($result['error']) && is_string($result['error'])) {
            $this->replySingleError($result['error'], $redirect);
            return;
        }

        $this->replySuccess();
    }

    private function makeRedirect(string $token): string
    {
        return self::BASE_REDIRECT . ($token !== '' ? ('?token=' . urlencode($token)) : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContext(string $token): array
    {
        return [
            'form'  => self::FORM_ID,
            'token' => $token !== '' ? '(present)' : '(empty)',
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
     * @param array<string, mixed> $form
     * @param array<string, mixed> $context
     */
    private function assertTurnstileIfRequired(array $form, array $context, string $redirect): bool
    {
        $rawFlags = $this->flash->take('security_flags', []);

        /** @var array<string, mixed> $flags */
        $flags = is_array($rawFlags) ? $rawFlags : [];

        $turnstileRequired = !empty($flags[self::TURNSTILE_FLAG]);

        $this->flash->put('security_flags', $flags);

        if (!$turnstileRequired) {
            return true;
        }

        return $this->turnstileGuard->assertValid([
            'form'        => $form,
            'redirect'    => $redirect,
            'context'     => $context,
            'token_field' => 'cf-turnstile-response',
        ]);
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function replyValidationErrors(array $errors, string $redirect): void
    {
        $list = $this->errorListNormalizer->normalize($errors);

        foreach ($list as $msg) {
            $this->flash->add('error', MessageManager::get($msg));
        }

        $this->responder->redirect($redirect);
    }

    private function replySingleError(string $errorCode, string $redirect): void
    {
        $this->flash->add('error', MessageManager::get($errorCode));
        $this->responder->redirect($redirect);
    }

    private function replySuccess(): void
    {
        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::AUTH_PASSWORD_RESET_SUCCESS)
        );

        $this->responder->redirect(self::LOGIN_REDIRECT);
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
