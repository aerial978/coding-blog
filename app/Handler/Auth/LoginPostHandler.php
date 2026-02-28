<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\SubmissionDelayGuard;

final class LoginPostHandler
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuard $honeypotGuard,
        private SubmissionDelayGuard $submissionDelayGuard,
        private RateLimitGuard $rateLimitGuard,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {    
        $email = $this->strOrEmpty($form['email'] ?? null);

        // 1) Honeypot (politique login : erreur + redirect)
        if (!$this->honeypotGuard->assertClean([
            'form'       => $form,
            'redirect'   => '/coding-blog/login',
            'flash_type' => 'error',
            'code'       => ErrorCode::AUTH_RETRY,
            'log_level'  => 'warning',
            'log_channel'=> 'auth',
            'context'    => [
                'email' => $email ?: null,
            ],

            'flags_bag' => 'security_flags',
            'set_flags' => ['turnstile_login' => true],
        ])) {
            return;
        }

        // 2) Submission delay
        if (!$this->submissionDelayGuard->assertPassed([
            'form_id'  => 'login',
            'redirect' => '/coding-blog/login',
            'context'  => [
                'email' => $email ?: null,
            ],
            'policy'   => [
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            'default'  => ['flash' => 'error', 'code' => ErrorCode::AUTH_RETRY],

            'flags_bag' => 'security_flags',
            'set_flags' => ['turnstile_login' => true],
        ])) {
            return;
        }

        // 3) Rate limit (login)
        // Choix standard : limiter “souple” pour éviter le bruteforce.
        if (!$this->rateLimitGuard->assertAllowed([
            'key'          => 'login',
            'limit'        => 5,
            'window_sec'   => 300,
            'redirect'     => '/coding-blog/login',
            'route_for_log'=> '/login',
            'flash_type'   => 'error',
            'put_old'      => ['email' => $email],
            'log_ctx'      => [
                'email' => $email ?: null,
            ],

            'flags_bag' => 'security_flags',
            'set_flags' => ['turnstile_login' => true],
        ])) {
            return;
        }

        // 4) Login service
        $result = $this->securityService->login($form);

        if (!empty($result['ok'])) {
            $this->flash->put('old', []);
            $this->flash->add('success', 'Connexion réussie.');
            $this->responder->redirect('/coding-blog');
            return;
        }

        // erreurs
        if (!empty($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $code) {
                $this->flash->add('error', MessageManager::get((string) $code));
            }
        } else {
            $this->flash->add('error', 'Échec de connexion.');
        }

        if (isset($result['old']) && is_array($result['old'])) {
            $this->flash->put('old', $result['old']);
        } else {
            $this->flash->put('old', ['email' => $email]);
        }

        $this->responder->redirect('/coding-blog/login');
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
