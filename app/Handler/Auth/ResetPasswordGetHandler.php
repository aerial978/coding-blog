<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;

final class ResetPasswordGetHandler
{
    public function __construct(
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private ResetPasswordServiceInterface $resetPasswordService,
    ) {
    }

    public function handle(string $token): void
    {
        $token = trim($token);

        $this->logEntry($token);
        $this->submissionDelay->markFormStart(FormId::RESET_PASSWORD);

        $turnstileRequired = $this->isTurnstileRequired();
        $check             = $this->resetPasswordService->validateResetToken($token);

        if (!$this->isValidTokenCheck($check)) {
            $this->replyInvalidToken($check);
            return;
        }

        $this->renderForm($token, $turnstileRequired);
    }

    private function logEntry(string $token): void
    {
        Logger::getLogger('auth')->info('reset_get_handler_entry', [
            'token_present' => $token !== '',
            'token_length'  => strlen($token),
            'token_prefix'  => $token !== '' ? substr($token, 0, 8) : '(empty)',
        ]);
    }

    private function isTurnstileRequired(): bool
    {
        $flags = $this->flash->take('security_flags', []);
        $flags = is_array($flags) ? $flags : [];

        return !empty($flags['turnstile_reset']);
    }

    /**
     * @param array<string, mixed> $check
     */
    private function isValidTokenCheck(array $check): bool
    {
        return !empty($check['ok']);
    }

    /**
     * @param array<string, mixed> $check
     */
    private function replyInvalidToken(array $check): void
    {
        $error = is_string($check['error'] ?? null) ? $check['error'] : '';

        $this->flash->add('error', MessageManager::get($error));
        $this->responder->redirect('/coding-blog/forgot-password');
    }

    private function renderForm(string $token, bool $turnstileRequired): void
    {
        $this->responder->render('security/reset-password.html.twig', [
            'title'              => 'Réinitialiser le mot de passe',
            'csrf_token'         => $this->csrf->generateToken(FormId::RESET_PASSWORD),
            'honeypot_name'      => $this->honeypot->fieldName(),
            'turnstile_required' => $turnstileRequired,
            'turnstile_enabled'  => $turnstileRequired,
            'token'              => $token,
        ]);
    }
}
