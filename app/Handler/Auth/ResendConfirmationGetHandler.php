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

final class ResendConfirmationGetHandler extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private ResponderInterface $responder,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
    ) {
        parent::__construct($view, $flash);
    }

    public function handle(): void
    {
        $old = $this->flash->take('old', []);

        if (empty($old)) {
            $this->submissionDelay->markFormStart('resend_confirm');
        }

        $rawFlags = $this->flash->take('security_flags', []);

        /** @var array<string, mixed> $flags */
        $flags = is_array($rawFlags) ? $rawFlags : [];

        Logger::getLogger('auth')->info('resend_get_flags', [
            'flags' => $flags,
        ]);

        $turnstileRequired = !empty($flags['turnstile_resend']);
        $turnstileEnabled  = $turnstileRequired;

        $this->responder->render(
            'security/resend-confirmation.html.twig',
            $this->withFlashes([
                'title'              => 'Resend confirmation',
                'csrf_token'         => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
                'old'                => $old,
                'honeypot_name'      => $this->honeypot->fieldName(),
                'turnstile_required' => $turnstileRequired,
                'turnstile_enabled'  => $turnstileEnabled,
                'turnstile_site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
            ])
        );
    }
}
