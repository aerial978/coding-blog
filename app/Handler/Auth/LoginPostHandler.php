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
use App\Service\Security\Contract\SecurityServiceInterface;

final class LoginPostHandler
{
    private const REDIRECT         = '/coding-blog/login';
    private const SUCCESS_REDIRECT = '/coding-blog';
    private const FORM_ID          = 'login';
    private const TURNSTILE_FLAG   = 'turnstile_login';
    private const RATE_LIMIT_KEY   = 'login';

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
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        $email   = $this->strOrEmpty($form['email'] ?? null);
        $context = $this->makeContext($email);

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
            'redirect'    => self::REDIRECT,
            'flash_type'  => 'error',
            'code'        => ErrorCode::AUTH_RETRY,
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
            'default' => ['flash' => 'error', 'code' => ErrorCode::AUTH_RETRY],
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
         *   flash_type: 'error',
         *   put_old: array<string, string>,
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
            'route_for_log' => '/login',
            'flash_type'    => 'error',
            'put_old'       => ['email' => $email],
            'log_ctx'       => $context,
        ], $this->turnstileStepUp());

        if (!$this->rateLimitGuard->assertAllowed($rateLimitOptions)) {
            return;
        }

        /** @var array<string, mixed> $result */
        $result = $this->securityService->login($form);

        if (!empty($result['ok'])) {
            $this->replySuccess();
            return;
        }

        $this->replyFailure($result, $email);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContext(string $email): array
    {
        return [
            'form'  => self::FORM_ID,
            'email' => $email !== '' ? $email : null,
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

    private function replySuccess(): void
    {
        $this->flash->put('old', []);
        $this->flash->add('success', 'Connexion réussie.');
        $this->responder->redirect(self::SUCCESS_REDIRECT);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function replyFailure(array $result, string $email): void
    {
        $this->flashLoginErrors($result);
        $this->flashOldLoginInput($result, $email);
        $this->responder->redirect(self::REDIRECT);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function flashLoginErrors(array $result): void
    {
        $errors = $result['errors'] ?? null;

        if (!is_array($errors) || $errors === []) {
            $this->flash->add('error', 'Échec de connexion.');
            return;
        }

        foreach ($errors as $code) {
            if (is_string($code)) {
                $this->flash->add('error', MessageManager::get($code));
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function flashOldLoginInput(array $result, string $email): void
    {
        $old = $result['old'] ?? null;

        if (is_array($old)) {
            $this->flash->put('old', $old);
            return;
        }

        $this->flash->put('old', ['email' => $email]);
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
