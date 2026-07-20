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

final class ResendConfirmationGetHandler
{
    public function __construct(
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
    ) {
    }

    public function handle(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart(FormId::RESEND_CONFIRM);
        }

        $rawFlags = $this->flash->take('security_flags', []);

        /** @var array<string, mixed> $flags */
        $flags = is_array($rawFlags) ? $rawFlags : [];

        Logger::getLogger('auth')->info('resend_get_flags', [
            'flags' => $flags,
        ]);

        $turnstileRequired = !empty($flags['turnstile_resend']);

        $this->responder->render(
            'security/resend-confirmation.html.twig',
            [
                'title'              => 'Resend confirmation',
                'csrf_token'         => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
                'old'                => is_array($old) ? $old : [],
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileRequired,
            ]
        );
    }
}
