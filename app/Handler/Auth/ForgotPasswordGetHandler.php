<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;

final class ForgotPasswordGetHandler
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
        $old = is_array($old) ? $old : [];

        if (empty($old)) {
            $this->submissionDelay->markFormStart(FormId::FORGOT_PASSWORD);
        }

        $rawFlags = $this->flash->take('security_flags', []);

        /** @var array<string, mixed> $flags */
        $flags = is_array($rawFlags) ? $rawFlags : [];

        Logger::getLogger('auth')->info('forgot_get_flags', [
            'flags' => $flags,
        ]);

        $turnstileRequired = !empty($flags['turnstile_forgot']);

        $this->responder->render(
            'security/forgot-password.html.twig',
            [
                'title'              => 'Forgot password',
                'csrf_token'         => $this->csrf->generateToken(FormId::FORGOT_PASSWORD),
                'old'                => $old,
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileRequired,
            ]
        );
    }
}
