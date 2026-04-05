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

final class ForgotPasswordPostHandler
{
    private const REDIRECT       = '/coding-blog/forgot-password';
    private const FORM_ID        = 'forgot_password';
    private const TURNSTILE_FLAG = 'turnstile_forgot';
    private const RATE_LIMIT_KEY = 'forgot_password';

    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuardInterface $honeypotGuard,
        private SubmissionDelayGuardInterface $submissionDelayGuard,
        private RateLimitGuardInterface $rateLimitGuard,
        // Optionnel : si vous voulez du "step-up" immédiat. Sinon, supprimez + retirez l'appel.
        private ?TurnstileGuardInterface $turnstileGuard = null,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        $identifier = $this->strOrEmpty($form['identifier'] ?? $form['email'] ?? null);

        if ($identifier === '') {
            $this->rejectEmptyIdentifier();
            return;
        }

        $context = $this->makeContext($identifier);

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
         * } $options
         */
        $options = array_merge([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
            'log_level'   => 'warning',
            'log_channel' => 'auth',
            'context'     => $context,
        ], $this->turnstileStepUp());

        if (!$this->honeypotGuard->assertClean($options)) {
            return;
        }

        /** @var array{
         *   form_id: string,
         *   redirect: string,
         *   context: array<string, mixed>,
         *   policy: array<string, array{flash: 'error'|'info'|'success'|'warning', code: string}>,
         *   default: array{flash: 'error'|'info'|'success'|'warning', code: string},
         *   min_sec: int,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $options
         */
        $options = array_merge([
            'form_id'  => self::FORM_ID,
            'redirect' => self::REDIRECT,
            'context'  => $context,
            'policy'   => [
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'  => ['flash' => 'error', 'code' => ErrorCode::AUTH_RETRY],
            'min_sec'  => 3,
        ], $this->turnstileStepUp());

        if (!$this->submissionDelayGuard->assertPassed($options)) {
            return;
        }

        /** @var array{
         *   key: string,
         *   limit: int,
         *   window_sec: int,
         *   redirect: string,
         *   route_for_log: string,
         *   flash_type: 'error',
         *   put_old: array<string, string>,
         *   log_ctx: array<string, mixed>,
         *   flags_bag?: string,
         *   set_flags?: array<string, mixed>
         * } $options
         */
        $options = array_merge([
            'key'           => self::RATE_LIMIT_KEY,
            'limit'         => 3,
            'window_sec'    => 600,
            'redirect'      => self::REDIRECT,
            'route_for_log' => '/forgot-password',
            'flash_type'    => 'error',
            'put_old'       => ['identifier' => $identifier],
            'log_ctx'       => $context,
        ], $this->turnstileStepUp());

        if (!$this->rateLimitGuard->assertAllowed($options)) {
            return;
        }

        if (!$this->assertTurnstileIfPresent($form, $context)) {
            return;
        }

        $this->securityService->forgotPassword($identifier);
        $this->replyNeutralSuccess($identifier);
    }

    private function rejectEmptyIdentifier(): void
    {
        $this->flash->add('error', MessageManager::get(ErrorCode::AUTH_FIELD_REQUIRED));
        $this->flash->put('old', ['identifier' => '']);
        $this->responder->redirect(self::REDIRECT);
    }

    /**
     * @return array<string,mixed>
     */
    private function makeContext(string $identifier): array
    {
        return [
            'form'       => self::FORM_ID,
            'identifier' => $identifier !== '' ? $identifier : null,
        ];
    }

    private function replyNeutralSuccess(string $identifier): void
    {
        $this->flash->add('success', MessageManager::get(ErrorCode::AUTH_PASSWORD_RESET_REQUESTED));
        $this->flash->put('old', ['identifier' => $identifier]);
        $this->responder->redirect(self::REDIRECT);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $context
     */
    private function assertTurnstileIfPresent(array $form, array $context): bool
    {
        if ($this->turnstileGuard === null) {
            return true;
        }

        $flags = $this->flash->take('security_flags', []);
        $flags = is_array($flags) ? $flags : [];

        $turnstileRequired = !empty($flags[self::TURNSTILE_FLAG]);

        // on remet les flags pour le GET suivant si besoin
        $this->flash->put('security_flags', $flags);

        if (!$turnstileRequired) {
            return true;
        }

        return $this->turnstileGuard->assertValid([
            'form'        => $form,
            'redirect'    => self::REDIRECT,
            'context'     => $context,
            'token_field' => 'cf-turnstile-response',
        ]);
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

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
