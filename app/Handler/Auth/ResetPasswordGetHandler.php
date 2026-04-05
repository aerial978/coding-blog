<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Controller\BaseController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;

final class ResetPasswordGetHandler extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private ResetPasswordServiceInterface $resetPasswordService,
    ) {
        parent::__construct($view, $flash);
    }

    public function handle(string $token): void
    {
        $token = trim($token);

        // temporaire //
        Logger::getLogger('auth')->info('reset_get_handler_entry', [
            'token_present' => $token !== '',
            'token_length'  => strlen($token),
            'token_prefix'  => $token !== '' ? substr($token, 0, 8) : '(empty)',
        ]);

        $this->submissionDelay->markFormStart('reset_password');

        $flags = $this->flash->take('security_flags', []);
        $flags = is_array($flags) ? $flags : [];

        $turnstileRequired = !empty($flags['turnstile_reset']);

        $check = $this->resetPasswordService->validateResetToken($token);

        if (!$check['ok']) {
            $error = is_string($check['error'] ?? null) ? $check['error'] : '';
            $this->flash->add('error', MessageManager::get($error));
            $this->responder->redirect('/coding-blog/forgot-password');
            return;
        }

        $this->responder->render('security/reset-password.html.twig', $this->withFlashes([
            'title'              => 'Réinitialiser le mot de passe',
            'csrf_token'         => $this->csrf->generateToken(FormId::RESET_PASSWORD),
            'honeypot_name'      => $this->honeypot->fieldName(),
            'turnstile_required' => $turnstileRequired,
            'turnstile_enabled'  => $turnstileRequired,
            'turnstile_site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
            'token'              => $token,
        ]));
    }
}
