<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Controller\BaseController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\Logger;
use App\Core\View;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;

final class ForgotPasswordGetHandler extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
    ) {
        parent::__construct($view, $flash);
    }

    public function handle(): void
    {
        $old = $this->flash->take('old', []);
        $old = is_array($old) ? $old : [];

        if (empty($old)) {
            $this->submissionDelay->markFormStart('forgot_password');
        }

        $rawFlags = $this->flash->take('security_flags', []);

        /** @var array<string, mixed> $flags */
        $flags = is_array($rawFlags) ? $rawFlags : [];

        Logger::getLogger('auth')->info('forgot_get_flags', [
            'flags' => $flags,
        ]);

        $turnstileRequired = !empty($flags['turnstile_forgot']);
        $turnstileEnabled  = $turnstileRequired;

        $this->responder->render(
            'security/forgot-password.html.twig',
            $this->withFlashes([
                'title'              => 'Forgot password',
                'csrf_token'         => $this->csrf->generateToken(FormId::FORGOT_PASSWORD),
                'old'                => $old,
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileEnabled,
                'turnstile_site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
            ])
        );
    }
}
