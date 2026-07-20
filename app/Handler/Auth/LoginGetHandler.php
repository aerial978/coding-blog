<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;

final class LoginGetHandler
{
    public function __construct(
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
    ) {
    }

    public function handle(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart(FormId::LOGIN);
        }

        $flags = $this->flash->take('security_flags', []);
        $flags = is_array($flags) ? $flags : [];

        $turnstileRequired = !empty($flags['turnstile_login']);

        $this->responder->render(
            'security/login.html.twig',
            [
                'title'              => 'Login',
                'csrf_token'         => $this->csrf->generateToken(FormId::LOGIN),
                'old'                => is_array($old) ? $old : [],
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileRequired,
            ]
        );
    }
}
