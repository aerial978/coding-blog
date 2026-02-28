<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\HoneypotGuard;
use App\Security\Guard\RateLimitGuard;
use App\Security\Guard\SubmissionDelayGuard;
use App\Security\Guard\TurnstileGuard;
use App\Service\Security\Contract\SecurityServiceInterface;

final class ForgotPasswordPostHandler
{
    public function __construct(
        private SecurityServiceInterface $securityService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private HoneypotGuard $honeypotGuard,
        private SubmissionDelayGuard $submissionDelayGuard,
        private RateLimitGuard $rateLimitGuard,
        // Optionnel : si vous voulez du "step-up" immédiat. Sinon, supprimez + retirez l'appel.
        private ?TurnstileGuard $turnstileGuard = null,
    ) {
    }

    /**
     * @param array<string,mixed> $form
     */
    public function handle(array $form): void
    {
        $identifier = $this->strOrEmpty($form['identifier'] ?? $form['email'] ?? null);

        // Base de contexte log (jamais de donnée sensible en clair si vous n’êtes pas sûr)
        $contextBase = [
            'form'       => 'forgot_password',
            'identifier' => $identifier !== '' ? $identifier : null,
        ];

        // 1) Honeypot
        if (!$this->honeypotGuard->assertClean([
            'form'       => $form,
            'redirect'   => '/coding-blog/forgot-password',
            'flash_type' => 'error',
            'code'       => ErrorCode::AUTH_RETRY, // neutre côté UI
            'log_level'  => 'warning',
            'log_channel'=> 'auth',
            'context'    => $contextBase,

            // Turnstile adaptatif
            'flags_bag'  => 'security_flags',
            'set_flags'  => ['turnstile_forgot' => true],
        ])) {
            return;
        }

        // 2) Submission delay
        if (!$this->submissionDelayGuard->assertPassed([
            'form_id'   => 'forgot_password',
            'redirect'  => '/coding-blog/forgot-password',
            'context'   => $contextBase,
            'policy'    => [
                // utile : le formulaire a expiré (max dépassé)
                'max_delay_exceeded' => ['flash' => 'error', 'code' => ErrorCode::AUTH_FORM_EXPIRED],
            ],
            // défaut neutre
            'default'   => ['flash' => 'error', 'code' => ErrorCode::AUTH_RETRY],

            'min_sec' => 3,

            // Turnstile adaptatif
            'flags_bag' => 'security_flags',
            'set_flags' => ['turnstile_forgot' => true],
        ])) {
            return;
        }

        // 3) Rate limit (forgot password)
        // Plus strict que login, typiquement 3/15min ou 5/1h selon votre politique
        if (!$this->rateLimitGuard->assertAllowed([
            'key'          => 'forgot_password',
            'limit'        => 3,
            'window_sec'   => 600, // 10 min
            'redirect'     => '/coding-blog/forgot-password',
            'route_for_log'=> '/forgot-password',
            'flash_type'   => 'error',
            'put_old'      => ['identifier' => $identifier],
            'log_ctx'      => $contextBase,

            // Turnstile adaptatif
            'flags_bag'    => 'security_flags',
            'set_flags'    => ['turnstile_forgot' => true],
        ])) {
            return;
        }

        // 4) Turnstile (step-up)
        // Si vous voulez exiger Turnstile uniquement quand la page l’affiche :
        // - votre GET met data-turnstile="1" quand flag présent
        // - le widget génère cf-turnstile-response
        // Ici : si TurnstileGuard existe, on peut le vérifier ; sinon on ignore.
        if ($this->turnstileGuard !== null) {
            $turnstileTokenPresent = is_string($form['cf-turnstile-response'] ?? null)
                && trim((string) $form['cf-turnstile-response']) !== '';

            // Option : vérifier seulement si un token est présent (donc widget affiché)
            // => évite de casser si Turnstile pas affiché
            if ($turnstileTokenPresent) {
                if (!$this->turnstileGuard->assertValid([
                    'form'        => $form,
                    'redirect'    => '/coding-blog/forgot-password',
                    'context'     => $contextBase,
                    'token_field' => 'cf-turnstile-response',
                ])) {
                    return;
                }
            }
        }

        // 5) Service : toujours neutre
        $this->securityService->forgotPassword($identifier);

        // UI neutre systématique (anti-énumération)
        $this->flash->add('success', MessageManager::get(ErrorCode::AUTH_PASSWORD_RESET_REQUESTED));

        // Conserver l’identifier (option UX)
        $this->flash->put('old', ['identifier' => $identifier]);

        $this->responder->redirect('/coding-blog/forgot-password');
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
