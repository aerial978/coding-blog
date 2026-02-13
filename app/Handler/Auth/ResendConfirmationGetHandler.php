<?php

declare(strict_types=1);

namespace App\Handler\Auth;

use App\Controller\BaseController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
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

        $this->responder->render('security/resend-confirmation.html.twig', $this->withFlashes([
            'title'         => 'Renvoyer le lien de confirmation',
            'csrf_token'    => $this->csrf->generateToken(FormId::RESEND_CONFIRM),
            'old'           => is_array($old) ? $old : [],
            'honeypot_name' => $this->honeypot->fieldName(),
        ]));
    }
}
