<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\Email2faPendingSessionInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;

final class Email2faGetHandler
{
    private const VIEW           = 'security/email-2fa.html.twig';
    private const LOGIN_REDIRECT = '/coding-blog/login';
    private const FORM_ID        = 'email_2fa_form';
    private const RESEND_FORM_ID = 'email_2fa_resend_form';

    public function __construct(
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private CsrfTokenInterface $csrf,
        private HoneypotValidatorInterface $honeypot,
        private SubmissionDelayValidatorInterface $submissionDelay,
        private Email2faPendingSessionInterface $email2faSession,
    ) {
    }

    public function handle(): void
    {
        if (!$this->email2faSession->hasPending() || $this->email2faSession->isExpired()) {
            $this->email2faSession->clear();

            $this->flash->add(
                'error',
                'Votre session de vérification a expiré. Veuillez vous reconnecter.'
            );

            $this->responder->redirect(self::LOGIN_REDIRECT);
            return;
        }

        $this->submissionDelay->markFormStart(self::FORM_ID);
        $this->submissionDelay->markFormStart(self::RESEND_FORM_ID);

        $this->responder->render(self::VIEW, [
            'title'             => 'Vérification de connexion',
            'csrf_token'        => $this->csrf->generateToken(self::FORM_ID),
            'csrf_token_resend' => $this->csrf->generateToken(self::RESEND_FORM_ID),
            'honeypot_name'     => $this->honeypot->fieldName(),
            'form_id'           => self::FORM_ID,
        ]);
    }
}
